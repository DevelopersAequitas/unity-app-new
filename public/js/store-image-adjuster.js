/**
 * Store Image Adjuster & Cropper Helper
 * Integrates Cropper.js with device browse, drag & drop, URL fallback, and live adjustment controls
 */
(function() {
    window.StoreImageAdjuster = {
        instances: {},

        init: function(config) {
            const state = {
                cropper: null,
                currentRatio: config.aspectRatio !== undefined ? config.aspectRatio : 1,
                currentRotation: 0,
                scaleX: 1,
                scaleY: 1
            };

            const fileInput = document.getElementById(config.fileInputId);
            const urlInput = config.urlInputId ? document.getElementById(config.urlInputId) : null;
            const hiddenData = config.hiddenDataId ? document.getElementById(config.hiddenDataId) : null;
            const dropzone = config.dropzoneId ? document.getElementById(config.dropzoneId) : null;
            const cropperContainer = document.getElementById(config.cropperContainerId);
            const imageEl = document.getElementById(config.imageElementId);
            const form = config.formId ? document.getElementById(config.formId) : (fileInput ? fileInput.closest('form') : null);

            function showCropper(src) {
                if (!src) return;
                if (cropperContainer) cropperContainer.classList.remove('d-none');
                if (dropzone) dropzone.classList.add('d-none');
                
                imageEl.src = src;
                
                if (state.cropper) {
                    state.cropper.destroy();
                    state.cropper = null;
                }
                
                state.currentRotation = 0;
                state.scaleX = 1;
                state.scaleY = 1;

                setTimeout(function() {
                    state.cropper = new Cropper(imageEl, {
                        aspectRatio: state.currentRatio,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.92,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                        ready: function() {
                            updatePreview();
                        },
                        crop: function() {
                            updatePreview();
                        }
                    });
                }, 120);
            }

            function updatePreview() {
                if (!state.cropper || !config.previewBoxId) return;
                const previewEl = document.getElementById(config.previewBoxId);
                if (!previewEl) return;
                try {
                    const canvas = state.cropper.getCroppedCanvas({ width: 200, height: 200 });
                    if (canvas) {
                        previewEl.src = canvas.toDataURL('image/jpeg', 0.85);
                    }
                } catch(e) {}
            }

            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files && e.target.files[0];
                    if (file) {
                        if (!file.type.startsWith('image/')) {
                            alert('Please select an image file (PNG, JPG, WEBP, GIF).');
                            return;
                        }
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            showCropper(evt.target.result);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            if (dropzone) {
                ['dragenter', 'dragover'].forEach(function(evtName) {
                    dropzone.addEventListener(evtName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.add('border-primary', 'bg-light');
                    }, false);
                });
                ['dragleave', 'drop'].forEach(function(evtName) {
                    dropzone.addEventListener(evtName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('border-primary', 'bg-light');
                    }, false);
                });
                dropzone.addEventListener('drop', function(e) {
                    const dt = e.dataTransfer;
                    const file = dt && dt.files && dt.files[0];
                    if (file && fileInput) {
                        fileInput.files = dt.files;
                        const event = new Event('change', { bubbles: true });
                        fileInput.dispatchEvent(event);
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    if (state.cropper && hiddenData) {
                        try {
                            const canvas = state.cropper.getCroppedCanvas({
                                maxWidth: 1600,
                                maxHeight: 1600,
                                imageSmoothingQuality: 'high'
                            });
                            if (canvas) {
                                hiddenData.value = canvas.toDataURL('image/jpeg', 0.92);
                            }
                        } catch(err) {
                            console.error('Error generating cropped canvas:', err);
                        }
                    }
                });
            }

            const instance = {
                state: state,
                showCropper: showCropper,
                zoom: function(delta) {
                    if (state.cropper) state.cropper.zoom(delta);
                },
                rotate: function(degree) {
                    if (state.cropper) state.cropper.rotate(degree);
                },
                flipH: function() {
                    if (state.cropper) {
                        state.scaleX = -state.scaleX;
                        state.cropper.scaleX(state.scaleX);
                    }
                },
                flipV: function() {
                    if (state.cropper) {
                        state.scaleY = -state.scaleY;
                        state.cropper.scaleY(state.scaleY);
                    }
                },
                setRatio: function(ratio) {
                    state.currentRatio = ratio;
                    if (state.cropper) state.cropper.setAspectRatio(ratio);
                },
                reset: function() {
                    if (state.cropper) state.cropper.reset();
                },
                clear: function() {
                    if (state.cropper) {
                        state.cropper.destroy();
                        state.cropper = null;
                    }
                    if (fileInput) fileInput.value = '';
                    if (urlInput) urlInput.value = '';
                    if (hiddenData) hiddenData.value = '';
                    if (cropperContainer) cropperContainer.classList.add('d-none');
                    if (dropzone) dropzone.classList.remove('d-none');
                    const previewEl = config.previewBoxId ? document.getElementById(config.previewBoxId) : null;
                    if (previewEl) previewEl.src = '';
                },
                loadFromUrl: function(url) {
                    const u = url || (urlInput ? urlInput.value.trim() : '');
                    if (!u) {
                        alert('Please enter an image URL.');
                        return;
                    }
                    showCropper(u);
                }
            };

            window.StoreImageAdjuster.instances[config.id] = instance;
            return instance;
        }
    };
})();
