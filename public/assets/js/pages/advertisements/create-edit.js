$(document).ready(function () {
    // Prevent submitting form when hitting Enter on keyboard
    $(window).keydown(function(event) {
        if (event.keyCode == 13) {
            event.preventDefault();
            return false;
        }
    });

    // Declare values
    let form = document.querySelector('#advertisementForm');
    let submitButton = document.querySelector('#btnSubmit');

    if (!form || !submitButton) return;

    // Initialize
    init();

    function init() {
        setupMediaTypeToggle();
        setupImageUpload();
        setupVideoUpload();
        setupDurationLimits();
        setupSubmit();

        // Set initial state - disable the inactive input
        const mediaType = document.querySelector('input[name="media_type"]:checked')?.value || 'image';
        toggleMediaInputs(mediaType);
    }

    function toggleMediaInputs(mediaType) {
        const imageInput = document.getElementById('imageInput');
        const videoInput = document.getElementById('videoInput');

        if (mediaType === 'video') {
            if (imageInput) {
                imageInput.disabled = true;
                imageInput.removeAttribute('name');
            }
            if (videoInput) {
                videoInput.disabled = false;
                videoInput.setAttribute('name', 'media');
            }
        } else {
            if (videoInput) {
                videoInput.disabled = true;
                videoInput.removeAttribute('name');
            }
            if (imageInput) {
                imageInput.disabled = false;
                imageInput.setAttribute('name', 'media');
            }
        }
    }

    function setupMediaTypeToggle() {
        const imageSection = document.getElementById('imagePreviewSection');
        const videoSection = document.getElementById('videoPreviewSection');
        const imageInput = document.getElementById('imageInput');
        const videoInput = document.getElementById('videoInput');
        const durationInput = document.getElementById('duration');

        if (!imageSection || !videoSection || !durationInput) return;

        document.querySelectorAll('input[name="media_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'video') {
                    imageSection.classList.add('d-none');
                    videoSection.classList.remove('d-none');
                    if (imageInput) imageInput.value = '';
                    durationInput.max = 300;
                    if (parseInt(durationInput.value) < 5) durationInput.value = 10;
                } else {
                    imageSection.classList.remove('d-none');
                    videoSection.classList.add('d-none');
                    if (videoInput) videoInput.value = '';
                    durationInput.max = 60;
                    if (parseInt(durationInput.value) > 60) durationInput.value = 30;
                }
                toggleMediaInputs(this.value);
                updateDurationHelp();
            });
        });
    }

    function setupImageUpload() {
        const dropzone = document.getElementById('imageDropzone');
        const imageInput = document.getElementById('imageInput');
        const newImagePreview = document.getElementById('newImagePreview');
        const imagePreviewImg = document.getElementById('imagePreviewImg');
        const removeImageBtn = document.getElementById('removeImageBtn');

        if (!dropzone || !imageInput) return;

        // Click to upload
        dropzone.addEventListener('click', () => imageInput.click());

        // Drag and drop
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('border-success', 'bg-light-success');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('border-success', 'bg-light-success');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-success', 'bg-light-success');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleImageFile(files[0]);
            }
        });

        // File input change
        imageInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleImageFile(this.files[0]);
            }
        });

        // Remove image button
        if (removeImageBtn) {
            removeImageBtn.addEventListener('click', function() {
                imageInput.value = '';
                if (newImagePreview) newImagePreview.classList.add('d-none');
                dropzone.classList.remove('d-none');
                if (imagePreviewImg) imagePreviewImg.src = '';
            });
        }

        function handleImageFile(file) {
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!validTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Type',
                    text: 'Please upload a JPEG, PNG, or JPG image file.'
                });
                return;
            }

            if (file.size > 10240 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Image file must not exceed 10MB.'
                });
                return;
            }

            const fileURL = URL.createObjectURL(file);
            if (imagePreviewImg) imagePreviewImg.src = fileURL;
            dropzone.classList.add('d-none');
            if (newImagePreview) newImagePreview.classList.remove('d-none');

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            imageInput.files = dataTransfer.files;
        }
    }

    function setupVideoUpload() {
        const dropzone = document.getElementById('videoDropzone');
        const videoInput = document.getElementById('videoInput');
        const newVideoPreview = document.getElementById('newVideoPreview');
        const videoPreviewPlayer = document.getElementById('videoPreviewPlayer');
        const removeVideoBtn = document.getElementById('removeVideoBtn');

        if (!dropzone || !videoInput) return;

        // Click to upload
        dropzone.addEventListener('click', () => videoInput.click());

        // Drag and drop
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('border-primary', 'bg-light-primary');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('border-primary', 'bg-light-primary');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-primary', 'bg-light-primary');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleVideoFile(files[0]);
            }
        });

        // File input change
        videoInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleVideoFile(this.files[0]);
            }
        });

        // Remove video button
        if (removeVideoBtn) {
            removeVideoBtn.addEventListener('click', function() {
                videoInput.value = '';
                if (newVideoPreview) newVideoPreview.classList.add('d-none');
                dropzone.classList.remove('d-none');
                if (videoPreviewPlayer) videoPreviewPlayer.src = '';
            });
        }

        function handleVideoFile(file) {
            const validTypes = ['video/mp4', 'video/webm', 'video/quicktime'];
            if (!validTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Type',
                    text: 'Please upload an MP4, WebM, or MOV video file.'
                });
                return;
            }

            if (file.size > 102400 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Video file must not exceed 100MB.'
                });
                return;
            }

            const fileURL = URL.createObjectURL(file);
            if (videoPreviewPlayer) videoPreviewPlayer.src = fileURL;
            dropzone.classList.add('d-none');
            if (newVideoPreview) newVideoPreview.classList.remove('d-none');

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            videoInput.files = dataTransfer.files;
        }
    }

    function setupDurationLimits() {
        const durationInput = document.getElementById('duration');
        if (!durationInput) return;

        durationInput.addEventListener('change', function() {
            const mediaType = document.querySelector('input[name="media_type"]:checked')?.value || 'image';
            const maxDuration = mediaType === 'video' ? 300 : 60;
            const minDuration = 5;

            if (parseInt(this.value) < minDuration) {
                this.value = minDuration;
            } else if (parseInt(this.value) > maxDuration) {
                this.value = maxDuration;
            }
        });
    }

    function updateDurationHelp() {
        const durationHelp = document.getElementById('durationHelp');
        const mediaType = document.querySelector('input[name="media_type"]:checked')?.value || 'image';

        if (durationHelp) {
            if (mediaType === 'video') {
                durationHelp.innerHTML = '<i class="fas fa-info-circle me-1"></i>Video: 5-300 seconds (5 minutes max)';
            } else {
                durationHelp.innerHTML = '<i class="fas fa-info-circle me-1"></i>Image: 5-60 seconds';
            }
        }
    }

    function setupSubmit() {
        submitButton.addEventListener('click', function (e) {
            e.preventDefault();

            const nameInput = document.getElementById('name');
            const durationInput = document.getElementById('duration');
            const mediaType = document.querySelector('input[name="media_type"]:checked')?.value || 'image';
            const isEdit = window.location.href.includes('edit');

            // Validate name
            if (!nameInput || !nameInput.value.trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Name Required',
                    text: 'Please enter an advertisement name.'
                });
                nameInput?.focus();
                return;
            }

            // Validate duration
            if (!durationInput || !durationInput.value || parseInt(durationInput.value) < 5) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Duration',
                    text: 'Duration must be at least 5 seconds.'
                });
                durationInput?.focus();
                return;
            }

            // Validate media for new ads
            if (!isEdit) {
                if (mediaType === 'image') {
                    const imageInput = document.getElementById('imageInput');
                    if (!imageInput || !imageInput.files || imageInput.files.length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Image Required',
                            text: 'Please upload an image for this advertisement.'
                        });
                        return;
                    }
                } else {
                    const videoInput = document.getElementById('videoInput');
                    if (!videoInput || !videoInput.files || videoInput.files.length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Video Required',
                            text: 'Please upload a video for this advertisement.'
                        });
                        return;
                    }
                }
            }

            // Submit form
            disableSubmitFormButton(submitButton);
            form.submit();
        });
    }
});
