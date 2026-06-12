<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Products\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductImageController extends Controller
{
    /**
     * Get all products without images
     */
    public function getProductsWithoutImages()
    {
        $products = Item::whereNull('image')
            ->orWhere('image', '')
            ->select('id', 'name', 'barcode', 'image')
            ->get();

        return response()->json($products);
    }

    /**
     * Upload product image (follows admin/ItemController pattern)
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
            'product_id' => 'required|exists:items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $product = Item::findOrFail($request->product_id);

            // Delete old image if exists (same pattern as admin)
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }

            // Store new image (same pattern as admin/ItemController)
            $image = $request->file('image');
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = 'img/products/';

            // Ensure directory exists
            if (! file_exists(public_path($up_location))) {
                mkdir(public_path($up_location), 0755, true);
            }

            // Move file to public directory
            $image->move(public_path($up_location), $img_name);
            $last_image = $up_location.$img_name; // Include extension

            return response()->json([
                'success' => true,
                'image_path' => $last_image,
                'url' => url($last_image),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload image',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update product image path
     */
    public function updateProductImage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'image_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $product = Item::findOrFail($id);
            $product->image = $request->image_path;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Product image updated successfully',
                'product' => $product,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update product',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch update - upload image and update product in one request
     * Follows admin/ItemController pattern for image storage
     */
    public function batchUploadAndUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
            'product_id' => 'required|exists:items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $product = Item::findOrFail($request->product_id);

            // Delete old image if exists (same pattern as admin)
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }

            // Store new image (same pattern as admin/ItemController)
            $image = $request->file('image');
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = 'img/products/';

            // Ensure directory exists
            if (! file_exists(public_path($up_location))) {
                mkdir(public_path($up_location), 0755, true);
            }

            // Move file to public directory
            $image->move(public_path($up_location), $img_name);
            $last_image = $up_location.$img_name; // Include extension

            // Update product with image path (with extension)
            $product->image = $last_image;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded and product updated successfully',
                'image_path' => $last_image,
                'url' => url($last_image),
                'product' => $product,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process request',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
