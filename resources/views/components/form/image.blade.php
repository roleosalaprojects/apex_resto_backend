@props([
    'image',
    'old_image',
    'title' => 'Avatar'
])

<!--begin::Image input placeholder-->
<style>
    .image-input-placeholder {
        background-image: url('{{asset("/assets/media/svg/shapes/abstract-4.svg")}}');
    }

    [data-bs-theme="dark"] .image-input-placeholder {
        background-image: url('{{asset("/assets/media/svg/shapes/abstract-4-dark.svg")}}');
    }
</style>
<!--end::Image input placeholder-->
<!--begin::Image input-->
<div
    class="image-input image-input-outline mb-10 fv-row"
    data-kt-image-input="true"
    style="background-image: url({{asset("/assets/media/svg/shapes/abstract-4.svg")}}"
>
    <!--begin::Image preview wrapper-->
    <div class="image-input-wrapper w-md-200px h-md-200px w-lg-300px h-lg-300px"
         style="background-image: url({{$image ? asset($image) : asset("/assets/media/svg/shapes/abstract-4-dark.svg")}})"
    ></div>
    <!--end::Image preview wrapper-->

    <!--begin::Edit button-->
    <label class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
           data-kt-image-input-action="change"
           data-bs-toggle="tooltip"
           data-bs-dismiss="click"
           title="Change {{ $title }}">
        <i class="fa-solid fa-pencil"></i>

        <!--begin::Inputs-->
        <input type="file" name="image" accept=".png, .jpg, .jpeg" value="{{ $image }}"/>
        <input type="hidden" name="old_image" value="{{ $old_image }}"/>
        <!--end::Inputs-->
    </label>
    <!--end::Edit button-->

    <!--begin::Cancel button-->
    <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
          data-kt-image-input-action="cancel"
          data-bs-toggle="tooltip"
          data-bs-dismiss="click"
          title="Cancel {{ $title }}"
    >
            <i class="fa-solid fa-trash"></i>
    </span>
    <!--end::Cancel button-->

    <!--begin::Remove button-->
    <span class="btn btn-icon btn-circle btn-color-muted btn-active-color-primary w-25px h-25px bg-body shadow"
          data-kt-image-input-action="remove"
          data-bs-toggle="tooltip"
          data-bs-dismiss="click"
          title="Remove {{ $title }}"
    >
        <i class="fa-solid fa-trash"></i>
    </span>
    <!--end::Remove button-->
    @error('image') <span class="text-danger">{{ $message }}</span> @enderror
</div>
<!--end::Image input-->