<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Bank;
use App\Models\Accounting\PosLog;

class HelperController extends Controller
{
    public function uploadImage($request, $section): string
    {
        $old_image = $request->old_image;

        $brand_image = $request->file('image');
        if ($brand_image) {
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($brand_image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = "img/$section/";
            $last_image = $up_location.$img_name;
            $brand_image->move($up_location, $last_image);
            if ($request->old_image) {
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }

            return $last_image;
        } else {
            return '';
        }
    }

    public function uploadMedia($request, $section, string $fieldName = 'media', string $oldFieldName = 'old_media'): string
    {
        $old_media = $request->input($oldFieldName);

        $media_file = $request->file($fieldName);
        if ($media_file) {
            $name_gen = hexdec(uniqid());
            $media_ext = strtolower($media_file->getClientOriginalExtension());
            $media_name = $name_gen.'.'.$media_ext;
            $up_location = "media/$section/";
            $last_media = $up_location.$media_name;
            $media_file->move($up_location, $media_name);
            if ($old_media && file_exists($old_media)) {
                unlink($old_media);
            }

            return $last_media;
        }

        return '';
    }

    public function actionButtonsReturnModal($model, $route, $modal): string
    {
        $model_name = $model->name;
        if ($model instanceof Bank) {
            $model_name = $model->bank_name.' - '.$model->account_number.' ('.$model->account_name.')';
        }
        $action = "<div class='d-flex justify-content-end flex-shrink-0 me-4'>";
        // View Button
        $action .= '<a href="'.route($route.'.show', $model->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
        // Edit Button
        $action .= '<span data-bs-toggle="modal" data-bs-target="#edit'.$modal.'Modal"><button class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$model->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></button></span>&nbsp';
        // Delete Button
        $action .= '<span data-bs-toggle="modal" data-bs-target="#delete'.$modal.'Modal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$model->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
        $action .= "\n<input type='hidden' id='name_$model->id' value='$model_name' />";
        $action .= '</div>';

        return $action;
    }

    public function logUserEvents($cash_in, $rendered, $cash_out, $type, $reason, $so_id, $pos_id, $store_id, $user_id)
    {
        return PosLog::create([
            'cash_in' => $cash_in,
            'rendered' => $rendered,
            'cash_out' => $cash_out,
            'type' => $type,
            'reason' => $reason,
            'so_id' => $so_id,
            'pos_id' => $pos_id,
            'store_id' => $store_id,
            'user_id' => $user_id,
        ]);
    }
}
