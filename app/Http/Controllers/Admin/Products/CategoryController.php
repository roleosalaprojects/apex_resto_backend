<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreRequest;
use App\Http\Requests\Category\UpdateRequest;
use App\Models\Employees\Role;
use App\Models\Pos\SaleLine;
use App\Models\Products\Category;
use App\Models\Products\Item;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Exceptions\Exception;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms) {
            $categories = Category::where('user_id', auth()->user()->user_id)->where('status', true)->get();

            return view('admin.products.categories.index', compact('categories', 'access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        $helper = new HelperController;
        $imagePath = $helper->uploadImage($request, 'categories');

        $category = Category::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath ?: null,
            'icon' => $validated['icon'] ?? null,
            'status' => true,
            'user_id' => auth()->user()->user_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category '.$category->name.' created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Category  $category
     * @return Response
     */
    public function show(Category $category)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms_read) {
            $items = Item::where('category_id', $category->id)->get();

            // dd($items);
            return view('admin.products.categories.show', compact('access', 'category', 'items'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateRequest $request, Category $category)
    {
        $validated = $request->validated();
        $helper = new HelperController;
        $imagePath = $helper->uploadImage($request, 'categories');

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
        ];

        if ($imagePath) {
            $data['image'] = $imagePath;
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return JsonResponse
     */
    public function destroy(Category $category)
    {
        // dd($category);
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms_delete) {
            Category::find($category->id)->update([
                'status' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ]);
        }

        return \response()->json([
            'success' => false,
            'message' => "You don't have rights to delete this category.",
        ]);
    }

    public function table()
    {
        $helper = new HelperController;
        $q = Category::query()->select('id', 'name', 'description', 'image', 'icon')->where('status', true);
        try {
            return DataTables($q)
                ->addColumn('actions', function (Category $category) use ($helper) {
                    return $helper->actionButtonsReturnModal($category, 'categories', 'Category');
                })
                ->rawColumns(['actions'])
                ->make(true);
        } catch (Exception $e) {
            \response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return [];
    }

    public function getCategory(Category $category)
    {
        return response()->json([
            'name' => $category->name,
            'description' => $category->description,
            'image' => $category->image ? asset($category->image) : null,
            'icon' => $category->icon,
        ]);
    }

    public function select(Request $request)
    {
        $name = $request->search;
        $categories = Category::where('name', 'LIKE', "%$name%")->take(50)->get();
        $data = [];
        foreach ($categories as $category) {
            $data[] = ['id' => $category->id, 'text' => $category->name];
        }

        return $data;
    }

    public function select_get(Request $request)
    {
        $categories = Category::whereIn('id', $request->categories)->select('id', 'name')->get();

        return $categories;
    }

    public function items(Request $request)
    {
        $output = '';
        $name = $request->search;
        // dd($name);
        $items = DB::table('categories as c')
            ->where('c.status', true)
            ->where(function ($query) use ($name) {
                $query->where('c.name', 'LIKE', '%'.$name.'%');
            })
            ->skip(0)
            ->take(50)
            ->get();
        $data = [];
        foreach ($items as $item) {
            $data[] = ['id' => $item->id, 'text' => $item->name];
        }
        echo json_encode($data);
    }

    // public function getCategory($id){
    //     $supplier = Category::find($id);
    //     $data[] = array("id"=>$supplier->id, "text"=>$supplier->name);
    //     echo json_encode($data);
    //     exit;
    // }

    public function tableShow(Request $request)
    {
        $start = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $q = DB::select("
            SELECT
            i.name as item,
            sum(sl.qty * sl.unit_qty) as sold,
            ROUND(sum(sl.sub_total) , 2) as amount,
            i.id as item_id
            FROM
            items i
            LEFT JOIN
            sale_lines sl
            on sl.item_id = i.id
            WHERE
            i.category_id = $request->category
            AND
            sl.created_at BETWEEN '$start' AND '$end'
            group by
            i.id, sl.item_id
        ");

        return datatables($q)->make(true);
    }

    public function summaryReport()
    {
        // $q = DB::select('
        //     SELECT
        //     sum(sl.sub_total),
        //     c.name as category,
        //     c.id as category_id
        //     from sale_lines sl
        //     LEFT JOIN
        //     sales s on s.id = sl.sales_id
        //     LEFT JOIN
        //     items i
        //     on i.id = sl.item_id
        //     LEFT JOIN
        //     categories c
        //     ON c.id = i.category_id
        //     WHERE sl.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW()
        //     group by c.id, i.category_id
        // ');
        // dd($q);
        $access = Role::find(auth()->user()->role_id);

        return view('admin.reports.reports.category', compact('access'));
    }

    public function summaryReportData(Request $request)
    {
        // dd($request->all());
        $start = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $q = DB::select("
            SELECT
            ROUND(sum(sl.sub_total), 2) as amount,
            c.name as category,
            c.id as category_id,
            sum(sl.qty * sl.unit_qty) as sold
            from sale_lines sl
            LEFT JOIN
            sales s on s.id = sl.sales_id
            LEFT JOIN
            items i
            on i.id = sl.item_id
            LEFT JOIN
            categories c
            ON c.id = i.category_id
            WHERE sl.created_at BETWEEN '$start' AND '$end'
            group by c.id, i.category_id
            ORDER BY sold DESC
        ");

        return datatables($q)->make(true);
    }

    public function showTable(Request $request, Category $category)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        //        dd($startDate, $endDate);
        $items = Item::where('category_id', $category->id)->get();
        $itemIds = [];
        foreach ($items as $item) {
            $itemIds[] = $item->id;
        }
        $data = SaleLine::whereIn('item_id', $itemIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('(SELECT name from items WHERE id = item_id) as item'),
                DB::raw('sum(qty * unit_qty) as item_sold'),
                DB::raw('sum(sub_total) as net_sales')
            )
            ->groupBy('item_id');

        //        dd($data->get());
        return response()->json(['data' => $data->get(), 'table' => DataTables($data)->make(true)]);
    }
}
