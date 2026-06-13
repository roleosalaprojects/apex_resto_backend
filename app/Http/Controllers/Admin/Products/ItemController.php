<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\BulkCategoryUpdateRequest;
use App\Http\Requests\Item\BulkPriceUpdateRequest;
use App\Http\Requests\Item\ImportItemsRequest;
use App\Jobs\ProcessBulkPriceUpdateJob;
use App\Jobs\ProcessCsvImportJob;
use App\Models\BulkOperationLog;
use App\Models\Employees\Role;
use App\Models\Pos\SaleLine;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\ItemUnit;
use App\Models\Products\PriceHistory;
use App\Models\Products\Unit;
use App\Models\Settings\Store;
use App\Services\CompositeItemService;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// use DataTables;

class /**/ ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Application|Factory|\Illuminate\Contracts\View\View|RedirectResponse|Redirector|View
     */
    public function index(Request $request)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms) {
            return view('admin.products.items.index', compact('access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (auth()->user()->role->itms_create) {
            $item = new Item;
            $locations = Store::with([
                'stocks' => function ($q) {
                    $q->where('item_id', null);
                },
            ])->where('status', true)->get();
            $selected_supplier = '';

            return view('admin.products.items.create', compact('item', 'locations'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required',
            'vatable' => 'required',
            'rate' => 'required',
            'markup' => 'required',
            'cost' => 'required',
            'type' => 'required',
            'image' => 'mimes:jpg,png,jpeg,gif,svg | nullable',
            'creditable_to_points' => 'nullable',
            'uom_label' => 'nullable|string|max:10',
            'cost_override' => 'nullable',
            'components' => 'nullable|array',
            'components.*.component_item_id' => 'required_with:components|integer|exists:items,id',
            'components.*.qty' => 'required_with:components|numeric|min:0.0001',
            'components.*.notes' => 'nullable|string|max:255',
        ]);
        $validate->validate();
        // if($validate->fails()){
        //     return redirect()->back()->withInput()->with('danger-callout', 'Something went wrong please try again!');
        // }

        $last_image = '';
        $brand_image = $request->file('image');
        if ($brand_image) {
            // Usual
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($brand_image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = 'img/products/';
            $last_image = $up_location.$img_name;
            $brand_image->move($up_location, $last_image);
            $last_image = $up_location.$name_gen;
        }

        // Check for duplicate barcodes in the database
        $duplicate = Item::where('barcode', $request->barcode)
            ->where('user_id', auth()->user()->user_id)->where('status', true)->get();
        // dd($duplicate);
        if (count($duplicate) > 0) {
            return redirect()->back()->withInput()->with('danger', 'Barcode is already taken');
        }

        $prev_cost = ($request->prev_cost) ? $request->prev_cost : 0;
        $price = ($request->main_price) ? $request->main_price : 0;
        $prev_price = ($request->prev_price) ? $request->prev_price : 0;
        $barcode = ($request->barcode) ? $request->barcode : '';
        $item = Item::create([
            'barcode' => $barcode,
            'name' => strtoupper($request->name),
            'category_id' => ($request->category) ? $request->category : 1,
            'vatable' => $request->vatable,
            'tax_id' => $request->rate,
            'markup' => $request->markup,
            'cost' => $request->cost,
            'prev_cost' => $prev_cost,
            'price' => $price,
            'prev_price' => $prev_price,
            'senior' => $request->senior,
            'pwd' => $request->pwd,
            'solo_parent' => $request->solo_parent,
            'naac' => $request->naac,
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'supplier_id' => $request->supplier,
            'discountable' => $request->discountable,
            'type' => $request->type,
            'image' => $last_image,
            'creditable_to_points' => $request->creditable_to_points == 'on',
            'uom_label' => $request->uom_label,
            'cost_override' => $request->cost_override == 'on',
        ]);

        if (is_array($request->components)) {
            try {
                app(CompositeItemService::class)->syncComponents(
                    $item,
                    array_values($request->components),
                    auth()->user()->user_id
                );
            } catch (\InvalidArgumentException $e) {
                return redirect()->back()->withInput()->with('danger', $e->getMessage());
            }
        }

        // Log initial price history
        if ($price > 0 || $request->cost > 0) {
            PriceHistory::create([
                'item_id' => $item->id,
                'old_price' => null,
                'new_price' => $price,
                'old_cost' => null,
                'new_cost' => $request->cost,
                'old_markup' => null,
                'new_markup' => $request->markup,
                'change_reason' => 'manual',
                'description' => 'Initial price set',
                'user_id' => auth()->user()->id,
            ]);
        }

        if ($request->uom_id) {
            $canManageUnitLock = (bool) (auth()->user()->role->unit_lock ?? false);
            $lockedInput = (array) $request->input('locked', []);

            for ($i = 0; $i < count($request->uom_id); $i++) {
                ItemUnit::create([
                    'qty' => $request->qty[$i],
                    'price' => number_format((float) $request->price[$i], 2, '.', ''),
                    'barcode' => $request->uom_barcode[$i],
                    'item_id' => $item->id,
                    'unit_id' => $request->uom_id[$i],
                    'status' => true,
                    // Only honor the lock flag when the actor has the unit_lock role.
                    // Users without it can still save items; they just can't toggle locks.
                    'locked' => $canManageUnitLock && ! empty($lockedInput[$i]),
                ]);
            }
        }
        if ($request->store) {
            $stores = is_array($request->store) ? $request->store : [$request->store];
            $stocks = is_array($request->stock) ? $request->stock : [$request->stock];
            for ($i = 0; $i < count($stores); $i++) {
                ItemStore::create([
                    'stock' => $stocks[$i] ?? 0,
                    'status' => true,
                    'store_id' => $stores[$i],
                    'item_id' => $item->id,
                ]);
            }
        }

        return redirect()->route('items.index')->with('success', 'Successfully added a new Item!');
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(Item $item)
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms_read) {
            // $sales = DB::table('sale_lines as sl')
            //     ->leftJoin('sales as s', 's.id', 'sl.sales_id')
            //     ->where('sl.item_id', $item->id)
            //     ->select("sl.*", 's.son', 's.type', 's.id as sale')
            //     ->get();

            $stocks = DB::table('item_stores as is')
                ->leftJoin('stores as s', 's.id', 'is.store_id')
                ->where('is.item_id', $item->id)
                ->select('is.*', 's.name as store')
                ->get();

            $item->load(['itemUnits.unit', 'wholesalePriceTiers']);

            return view('admin.products.items.show', compact('access', 'item', 'stocks'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Item $item)
    {
        if (auth()->user()->role->itms_update) {
            $locations = Store::with([
                'stocks' => function ($q) use ($item) {
                    $q->where('item_id', $item->id);
                },
            ])->where('status', true)->get();

            // return $item;

            return view('admin.products.items.edit', compact('item', 'locations'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Update the specified resource in storage.
     *
     * @return RedirectResponse
     */
    public function update(Request $request, Item $item)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required',
            'vatable' => 'required',
            'rate' => 'required',
            'markup' => 'required',
            'cost' => 'required',
            'type' => 'required',
            'image' => 'mimes:jpg,png,jpeg,gif,svg | nullable',
            'creditable_to_points' => 'nullable',
            'uom_label' => 'nullable|string|max:10',
            'cost_override' => 'nullable',
            'components' => 'nullable|array',
            'components.*.component_item_id' => 'required_with:components|integer|exists:items,id',
            'components.*.qty' => 'required_with:components|numeric|min:0.0001',
            'components.*.notes' => 'nullable|string|max:255',
        ]);
        $validate->validate();

        $old_image = $request->old_image;

        $brand_image = $request->file('image');
        if ($brand_image) {
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($brand_image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = 'img/products/';
            $last_image = $up_location.$img_name;
            $brand_image->move($up_location, $last_image);
            if ($request->old_image) {
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }
            Item::where('id', $item->id)->update([
                'image' => $last_image,
            ]);
        }

        if ($request->barcode != $item->barcode) {
            // Check for duplicate barcodes in the database
            $duplicate = Item::where('barcode', $request->barcode)->where('user_id', auth()->user()->id)->where('status', true)->get();
            // dd($duplicate);
            if (count($duplicate) > 0) {
                return redirect()->back()->withInput()->with('danger-callout', 'Barcode is already taken');
            }
        }
        $barcode = ($request->barcode) ? $request->barcode : '';
        $prev_cost = ($request->prev_cost) ? $request->prev_cost : 0;
        $price = ($request->main_price) ? $request->main_price : 0;
        $prev_price = ($request->prev_price) ? $request->prev_price : 0;

        // Log price history if price or cost changed
        $priceChanged = $item->price != $price;
        $costChanged = $item->cost != $request->cost;
        if ($priceChanged || $costChanged) {
            PriceHistory::create([
                'item_id' => $item->id,
                'old_price' => $item->price,
                'new_price' => $price,
                'old_cost' => $item->cost,
                'new_cost' => $request->cost,
                'old_markup' => $item->markup,
                'new_markup' => $request->markup,
                'change_reason' => 'manual',
                'description' => $priceChanged && $costChanged
                    ? 'Price and cost updated'
                    : ($priceChanged ? 'Price updated' : 'Cost updated'),
                'user_id' => auth()->user()->id,
            ]);
        }

        Item::where('id', $item->id)->update([
            'barcode' => $barcode,
            'name' => strtoupper($request->name),
            'category_id' => $request->category,
            'vatable' => $request->vatable,
            'tax_id' => $request->rate,
            'markup' => $request->markup,
            'cost' => $request->cost,
            'prev_cost' => $prev_cost,
            'price' => $price,
            'prev_price' => $prev_price,
            'senior' => $request->senior,
            'pwd' => $request->pwd,
            'solo_parent' => $request->solo_parent,
            'naac' => $request->naac,
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'supplier_id' => $request->supplier,
            'discountable' => $request->discountable,
            'type' => $request->type,
            'creditable_to_points' => $request->creditable_to_points == 'on',
            'uom_label' => $request->uom_label,
            'cost_override' => $request->cost_override == 'on',
        ]);

        if ($request->has('components')) {
            try {
                app(CompositeItemService::class)->syncComponents(
                    $item->fresh(),
                    array_values((array) $request->components),
                    auth()->user()->user_id
                );
            } catch (\InvalidArgumentException $e) {
                return redirect()->back()->withInput()->with('danger-callout', $e->getMessage());
            }
        }
        // Capture the previous lock state per unit_id BEFORE deleting, so users
        // without the unit_lock role can still edit items without losing locks
        // that a higher-privileged user previously configured.
        $previousLocks = ItemUnit::query()
            ->where('item_id', $item->id)
            ->pluck('locked', 'unit_id')
            ->toArray();

        ItemUnit::where('item_id', $item->id)->delete();
        if ($request->uom_id) {
            $canManageUnitLock = (bool) (auth()->user()->role->unit_lock ?? false);
            $lockedInput = (array) $request->input('locked', []);

            for ($i = 0; $i < count($request->uom_id); $i++) {
                $unitId = (int) $request->uom_id[$i];
                $locked = $canManageUnitLock
                    ? ! empty($lockedInput[$i])
                    : (bool) ($previousLocks[$unitId] ?? false);

                ItemUnit::create([
                    'qty' => $request->qty[$i],
                    'price' => number_format((float) $request->price[$i], 2, '.', ''),
                    'barcode' => $request->uom_barcode[$i],
                    'item_id' => $item->id,
                    'unit_id' => $unitId,
                    'status' => true,
                    'locked' => $locked,
                ]);
            }
        }
        ItemStore::where('item_id', $item->id)->delete();
        if ($request->store) {
            $stores = is_array($request->store) ? $request->store : [$request->store];
            $stocks = is_array($request->stock) ? $request->stock : [$request->stock];
            for ($i = 0; $i < count($stores); $i++) {
                ItemStore::create([
                    'stock' => $stocks[$i] ?? 0,
                    'status' => true,
                    'store_id' => $stores[$i],
                    'item_id' => $item->id,
                ]);
            }
        }

        return redirect()->route('items.show', $item->id)->with('info', 'Item successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return RedirectResponse
     */
    public function destroy(Item $item)
    {
        //
        // dd($item->id);
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms_delete) {
            Item::find($item->id)->update([
                'status' => false,
            ]);
            ItemUnit::where('item_id', $item->id)->update([
                'status' => false,
            ]);

            return redirect()->route('items.index')->with('success', 'Item successfully deleted!');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function table(Request $request)
    {
        $query = Item::with([
            'stocks' => function ($q) {
                $q->select('stock', 'item_id', 'store_id');
                $q->with([
                    'store' => function ($q) {
                        $q->select('id', 'name');
                        $q->where('status', true);
                    },
                ]);
                $q->where('status', true);
            },
            'category' => function ($q) {
                $q->select('id', 'name');
            },
            'supplier' => function ($q) {
                $q->select('id', 'name');
            },
        ])
            ->where('status', true);

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $query = $query->get();

        return datatables($query)
            ->addColumn('actions', function (Item $item) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                if (auth()->user()->role->itms_read) {
                    $action .= '<a href="'.route('items.show', $item->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                if (auth()->user()->role->itms_update) {
                    // Edit Button
                    $action .= '<a href="'.route('items.edit', $item->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$item->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->itms_delete) {
                    // Delete Button
                    $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$item->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                    $action .= '<input type="hidden" id="name_'.$item->id.'" value="'.$item->name.'" />';
                    $action .= '<form method="POST" action="'.route('items.destroy', $item->id).'" id="form_delete_'.$item->id.'" value="'.$item->name.'">'.method_field('DELETE').csrf_field().'</form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function select(Request $request)
    {
        $name = $request->term;
        $items = Item::where('status', true)
            ->where(function ($query) use ($name) {
                $query->where('name', 'LIKE', "%$name%");
                $query->orWhere('barcode', 'LIKE', "%$name%");
            })
            ->take(100)
            ->orderBy('name')
            ->get();
        $data = [];
        foreach ($items as $item) {
            $data[] = ['id' => $item->id, 'text' => $item->name];
        }

        return $data;
    }

    public function getItem(Item $item)
    {
        // return $item;
        $q = $item->with([
            'itemUnits' => function ($q) {
                $q->with(['unit']);
                $q->where('status', true);

                return $q;
            },
            'itemStores' => function ($q) {
                $q->with(['store']);
                $q->where('status', true);

                return $q;
            },
            'tax' => function ($q) {
                $q->where('status', true);

                return $q;
            },
            'category' => function ($q) {
                $q->select('id', 'name');
            },
            'supplier' => function ($q) {
                $q->select('id', 'name');
            },
        ])->find($item->id);

        return $q;
    }

    public function ItemBarcodeChecker(Request $request)
    {
        $output = 0;
        if ($request->code) {
            $intitial = DB::table('items as i')
                ->leftJoin('item_units as iu', 'iu.item_id', 'i.id')
                ->where('i.barcode', $request->code)
                ->orWhere('iu.barcode', $request->code)
                ->select('i.barcode as item_barcode', 'iu.barcode as unit_barcode')
                ->get();
            $output = count($intitial);
        }

        return Response($output);
    }

    public function ItemNameFixer()
    {

        $items = Item::where('user_id', auth()->user()->user_id)->get();
        // dd($items->first());
        foreach ($items as $item) {
            // dd($item->name);
            Item::find($item->id)->update([
                'name' => strtoupper($item->name),
            ]);
        }

        return redirect()->route('items.index')->with('msg', 'Success!');
    }

    public function PrintLabel()
    {
        $items = Item::where('items.user_id', auth()->user()->user_id)->where('items.status', 1)
            ->leftJoin('item_units as iu', 'iu.item_id', 'items.id')
            ->leftJoin('units as u', 'u.id', 'iu.unit_id')
            ->select('items.*', 'u.name as unit', 'u.id as unit_id', 'iu.price as unit_price')
            ->orderBy('name')
            ->get();
        $access = Role::find(auth()->user()->role_id);

        return view('admin.products.items.print_labels', compact('access', 'items'));
    }

    public function GetItemForLabel(Request $request)
    {
        $output = '';
        $values = explode('-', $request->name);
        $unit = '';
        $price = 0;
        $unit_id = '';
        if ($values[1] == '') {
            $item = Item::where('id', $request->name)->first();
            $unit = 'PC';
            if ($price == 0) {
                $price = $item->cost + (($item->margin / 100) * $item->cost);
            }
        } else {
            $item_unit = ItemUnit::where('item_id', $values[0])->where('unit_id', $values[1])->first();
            $unit_info = Unit::where('id', $values[1])->first();
            $unit = $unit_info->name;
            $unit_id = $unit_info->id;
            $item = Item::where('id', $values[0])->first();
            $price = $item_unit->price;
        }
        $output = "<tr><td><input type='hidden' name='items[]' value='$item->id'><input type='hidden' name='units[]' value='$unit_id'>$item->name - $unit (₱$price)</td></tr>";

        // dd($output);
        return Response($output);
    }

    public function GetAllItemForLabel()
    {
        $output = '';
        $all = collect();
        $items = Item::where('user_id', auth()->user()->user_id)->where('status', 1)->orderBy('name')->get();
        foreach ($items as $item) {
            $price = $item->price;
            if ($price == 0) {
                $price = $item->cost + (($item->margin / 100) * $item->cost);
            }
            $all->push(['item_id' => $item->id, 'name' => $item->name, 'unit' => 'PC', 'price' => $price, 'unit_id' => '']);
            $item_unit = DB::table('item_units as iu')
                ->leftJoin('units as u', 'u.id', 'iu.unit_id')
                ->where('item_id', $item->id)
                ->select('iu.price', 'u.name', 'u.id as unit_id')
                ->get();
            foreach ($item_unit as $unit) {
                $all->push(['item_id' => $item->id, 'name' => $item->name, 'unit' => $unit->name, 'price' => $unit->price, 'unit_id' => $unit->unit_id]);
            }
        }
        // dd($all->all());
        foreach ($all as $item) {
            $output .= "<tr><td><input type='hidden' name='items[]' value='".$item['item_id']."'><input type='hidden' name='units[]' value='".$item['unit_id']."'>".$item['name'].'e - '.$item['unit'].' (₱'.$item['price'].')</td></tr>';
        }

        return Response($output);
    }

    public function ReadyItems(Request $request)
    {
        // dd($request->all());
        $items = collect();
        if ($request->items) {
            for ($i = 0; $i < count($request->items); $i++) {
                $unit = 'PC';
                $price = 0;
                $item = Item::where('id', $request->items[$i])->first();
                $price = $item->$price;
                $barcode = $item->barcode;
                if ($price == 0) {
                    $price = $item->cost + (($item->margin / 100) * $item->cost);
                }

                if ($request->units[$i] != '') {
                    $item_unit = ItemUnit::where('item_id', $request->items[$i])->where('unit_id', $request->units[$i])->first();
                    $price = $item_unit->price;
                    $unit_name = Unit::where('id', $request->units[$i])->first();
                    $unit = $unit_name->name;
                    $barcode = $item_unit->barcode;
                }

                $items->push(['description' => $item->name, 'price' => $price, 'unit' => $unit, 'barcode' => $barcode]);

            }
        }
        // dd($items);
        $access = Role::find(auth()->user()->role_id);

        return view('admin.products.items.ready-for-print', compact('items', 'access'));
    }

    public function indexTable(Request $request)
    {
        $query = Item::with([
            'stocks' => function ($q) {
                $q->select('stock', 'item_id', 'store_id');
                $q->with([
                    'store' => function ($q) {
                        $q->select('id', 'name');
                        $q->where('status', true);
                    },
                ]);
                $q->where('status', true);
            },
            'category' => function ($q) {
                $q->select('id', 'name');
            },
            'supplier' => function ($q) {
                $q->select('id', 'name');
            },
        ])
            ->where('status', true)
            ->get();

        return datatables($query)
            ->addColumn('actions', function () {})
            ->make(true);
    }

    public function insightTable(Item $item, Request $request)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        // Years
        $dateDiffYears = $startDate->diffInYears($endDate);
        // Months
        $dateDiffMonths = $startDate->diffInMonths($endDate);
        // Days
        $dateDiffDays = $startDate->diffInDays($endDate);

        $query = SaleLine::leftJoin('sales', 'sales.id', 'sale_lines.sales_id')->where('item_id', $item->id)->whereBetween('sale_lines.created_at', [$startDate, $endDayString]);
        if ($dateDiffYears == 0) {
            if ($dateDiffMonths == 0) {
                if ($dateDiffDays > 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, sale_lines.sub_total, -sale_lines.sub_total)) as sales'),
                        DB::raw('sum(if(type = 0, qty * unit_qty, -(qty * unit_qty))) as qty'),
                        DB::raw('DATE_FORMAT(sales.created_at, "%b %d, %y") as `time`'))
                        ->groupBy(DB::raw('day(sales.created_at)'));
                } elseif ($dateDiffDays == 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, sale_lines.sub_total, -sale_lines.sub_total)) as sales'),
                        DB::raw('sum(if(type = 0, qty * unit_qty, -(qty * unit_qty))) as qty'),
                        DB::raw('DATE_FORMAT(sales.created_at, "%h:00 %p") as `time`'),
                    )
                        ->groupBy(DB::raw('hour(sales.created_at)'));
                }
            } else {
                $query->select(
                    DB::raw('sum(if(type = 0, sale_lines.sub_total, -sale_lines.sub_total)) as sales'),
                    DB::raw('sum(if(type = 0, qty * unit_qty, -(qty * unit_qty))) as qty'),
                    DB::raw('DATE_FORMAT(sales.created_at, "%b %Y") as `time`'),
                )
                    ->groupBy(DB::raw('month(sales.created_at)'));
            }
        } else {
            $query->select(
                DB::raw('sum(if(type = 0, sale_lines.sub_total, -sale_lines.sub_total)) as sales'),
                DB::raw('sum(if(type = 0, qty * unit_qty, -(qty * unit_qty))) as qty'),
                DB::raw('DATE_FORMAT(sales.created_at, "%Y") as `time`'),
            )
                ->groupBy(DB::raw('year(sales.created_at)'));
        }

        if ($request->store_select) {
            $query->where('sales.store_id', $request->store_select);
        }

        $prices = SaleLine::where('item_id', $item->id)
            ->whereBetween('sale_lines.created_at', [$startDate, $endDayString])
            ->leftJoin('sales', 'sales.id', 'sale_lines.sales_id')
            ->select(
                DB::raw('sum(if(type = 0, sub_total, -sub_total)) as total'),
                DB::raw('sum(if(type = 0, sub_total - (qty * sale_lines.cost * unit_qty), -(sub_total - (qty * sale_lines.cost * unit_qty)))) as revenue'),
                DB::raw('avg(price) as avg'),
                DB::raw('min(price) as min'),
                DB::raw('max(price) as max'),
                DB::raw('sum(if(type = 0, qty * unit_qty, -(qty * unit_qty))) as qty'),
            )
            ->first();

        return response()->json(['insight' => DataTables($query)->make(true), 'prices' => $prices]);
    }

    public function bulkEdit(): View|RedirectResponse
    {
        if (! auth()->user()->role->itms_update) {
            return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        $categories = Category::where('status', true)->orderBy('name')->get(['id', 'name']);
        $stores = Store::where('status', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.products.items.bulk_edit', compact('categories', 'stores'));
    }

    public function bulkUpdatePrices(BulkPriceUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $itemIds = $validated['item_ids'];
        $itemCount = count($itemIds);

        if ($itemCount > 50) {
            $log = BulkOperationLog::create([
                'type' => 'price_update',
                'user_id' => auth()->id(),
                'total_records' => $itemCount,
                'status' => 'pending',
            ]);

            ProcessBulkPriceUpdateJob::dispatch(
                $log,
                $itemIds,
                $validated['update_type'],
                $validated['field'],
                (float) $validated['value'],
                $validated['direction'],
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'async' => true,
                'log_id' => $log->id,
                'message' => "Processing {$itemCount} items in the background.",
            ]);
        }

        $successCount = 0;
        $errors = [];

        foreach ($itemIds as $itemId) {
            try {
                DB::transaction(function () use ($itemId, $validated) {
                    $item = Item::findOrFail($itemId);
                    $field = $validated['field'];
                    $oldValue = $item->{$field};
                    $newValue = $this->calculateNewValue(
                        $oldValue,
                        $validated['update_type'],
                        (float) $validated['value'],
                        $validated['direction']
                    );

                    $priceHistoryData = [
                        'item_id' => $item->id,
                        'change_reason' => 'bulk',
                        'description' => 'Bulk price update',
                        'user_id' => auth()->id(),
                    ];

                    if ($field === 'price') {
                        $item->prev_price = $item->price;
                        $item->price = $newValue;
                        $priceHistoryData['old_price'] = $oldValue;
                        $priceHistoryData['new_price'] = $newValue;
                    } elseif ($field === 'cost') {
                        $item->prev_cost = $item->cost;
                        $item->cost = $newValue;
                        $priceHistoryData['old_cost'] = $oldValue;
                        $priceHistoryData['new_cost'] = $newValue;
                    } elseif ($field === 'markup') {
                        $item->markup = $newValue;
                        $priceHistoryData['old_markup'] = $oldValue;
                        $priceHistoryData['new_markup'] = $newValue;
                    }

                    $item->save();
                    PriceHistory::create($priceHistoryData);
                });
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = ['item_id' => $itemId, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'async' => false,
            'updated' => $successCount,
            'failed' => count($errors),
            'errors' => $errors,
            'message' => "Updated {$successCount} items successfully.",
        ]);
    }

    public function bulkUpdateCategory(BulkCategoryUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $itemIds = $validated['item_ids'];
        $categoryId = $validated['category_id'];

        $updated = Item::whereIn('id', $itemIds)
            ->where('status', true)
            ->update(['category_id' => $categoryId]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'message' => "Updated category for {$updated} items successfully.",
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $itemIds = $request->input('item_ids', []);
        $stores = Store::where('status', true)->orderBy('name')->pluck('name', 'id');

        $query = Item::query()
            ->with(['category:id,name', 'supplier:id,name', 'itemStores.store:id,name'])
            ->where('status', true);

        if (! empty($itemIds)) {
            $query->whereIn('id', $itemIds);
        }

        $items = $query->orderBy('name')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="items_export_'.now()->format('Y-m-d_His').'.csv"',
        ];

        return response()->stream(function () use ($items, $stores) {
            $handle = fopen('php://output', 'w');

            $headerRow = ['barcode', 'name', 'category', 'supplier', 'cost', 'markup', 'price', 'vatable', 'type', 'status'];
            foreach ($stores as $storeName) {
                $headerRow[] = 'stock_'.str_replace(' ', '_', $storeName);
            }
            fputcsv($handle, $headerRow);

            foreach ($items as $item) {
                $row = [
                    $item->barcode,
                    $item->name,
                    $item->category?->name ?? '',
                    $item->supplier?->name ?? '',
                    $item->cost,
                    $item->markup,
                    $item->price,
                    $item->vatable ? '1' : '0',
                    $item->type === 0 ? 'PC' : 'KG',
                    $item->status ? '1' : '0',
                ];

                $itemStocks = $item->itemStores->keyBy(fn ($is) => $is->store?->name);
                foreach ($stores as $storeName) {
                    $row[] = $itemStocks->get($storeName)?->stock ?? 0;
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function importCsv(ImportItemsRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $updateExisting = $request->boolean('update_existing', false);

        $path = $file->store('imports');
        $fullPath = Storage::path($path);

        $lineCount = 0;
        $handle = fopen($fullPath, 'r');
        if ($handle) {
            while (fgets($handle) !== false) {
                $lineCount++;
            }
            fclose($handle);
        }
        $recordCount = max(0, $lineCount - 1);

        $log = BulkOperationLog::create([
            'type' => 'import',
            'user_id' => auth()->id(),
            'total_records' => $recordCount,
            'status' => 'pending',
        ]);

        ProcessCsvImportJob::dispatch($log, $path, $updateExisting, auth()->id());

        return response()->json([
            'success' => true,
            'log_id' => $log->id,
            'total_records' => $recordCount,
            'message' => "Import started. Processing {$recordCount} records.",
        ]);
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $stores = Store::where('status', true)->orderBy('name')->pluck('name');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="items_import_template.csv"',
        ];

        return response()->stream(function () use ($stores) {
            $handle = fopen('php://output', 'w');

            $headerRow = ['barcode', 'name', 'category', 'supplier', 'cost', 'markup', 'price', 'vatable', 'type', 'status'];
            foreach ($stores as $storeName) {
                $headerRow[] = 'stock_'.str_replace(' ', '_', $storeName);
            }
            fputcsv($handle, $headerRow);

            $exampleRow = ['1234567890123', 'SAMPLE PRODUCT', 'Beverages', 'Sample Supplier', '100.00', '20', '120.00', '1', 'PC', '1'];
            foreach ($stores as $storeName) {
                $exampleRow[] = '0';
            }
            fputcsv($handle, $exampleRow);

            fclose($handle);
        }, 200, $headers);
    }

    public function getBulkOperationStatus(BulkOperationLog $log): JsonResponse
    {
        return response()->json([
            'id' => $log->id,
            'type' => $log->type,
            'status' => $log->status,
            'total_records' => $log->total_records,
            'processed_records' => $log->processed_records,
            'success_records' => $log->success_records,
            'failed_records' => $log->failed_records,
            'progress_percent' => $log->progress_percent,
            'errors' => $log->errors,
            'started_at' => $log->started_at?->format('M d, Y H:i:s'),
            'completed_at' => $log->completed_at?->format('M d, Y H:i:s'),
        ]);
    }

    private function calculateNewValue(float $currentValue, string $updateType, float $value, string $direction): float
    {
        if ($updateType === 'fixed') {
            $change = $value;
        } else {
            $change = $currentValue * ($value / 100);
        }

        if ($direction === 'decrease') {
            $newValue = $currentValue - $change;
        } else {
            $newValue = $currentValue + $change;
        }

        return max(0, round($newValue, 2));
    }

    public function priceHistory(Item $item, Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $history = PriceHistory::where('item_id', $item->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('user:id,name')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->created_at->format('M d, Y H:i'),
                    'old_price' => $record->old_price,
                    'new_price' => $record->new_price,
                    'old_cost' => $record->old_cost,
                    'new_cost' => $record->new_cost,
                    'change_reason' => $record->change_reason,
                    'description' => $record->description,
                    'changed_by' => $record->user->name ?? 'System',
                ];
            });

        return response()->json([
            'data' => $history,
            'chart' => [
                'dates' => $history->pluck('date'),
                'prices' => $history->pluck('new_price'),
                'costs' => $history->pluck('new_cost'),
            ],
        ]);
    }
}
