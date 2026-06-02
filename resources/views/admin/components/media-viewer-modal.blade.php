@props([
    'modalId' => 'mediaViewerModal',
])

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $modalId }}Label">Media</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-media-container>
                <p class="text-muted mb-0">No media available.</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-media-modal="{{ $modalId }}"]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('{{ $modalId }}');
            const container = modal.querySelector('[data-media-container]');
            const sourceId = button.getAttribute('data-media-source');
            const scriptTag = document.getElementById(sourceId);
            let items = [];

            if (scriptTag) {
                try {
                    items = JSON.parse(scriptTag.textContent || '[]');
                } catch (error) {
                    items = [];
                }
            }

            container.innerHTML = '';

            if (!Array.isArray(items) || items.length === 0) {
                container.innerHTML = '<p class="text-muted mb-0">No media available.</p>';
                return;
            }

            items.forEach((item, index) => {
                let url = null;

                if (typeof item === 'string') {
                    url = item;
                } else if (item && typeof item === 'object') {
                    url = item.url || item.file_url || item.media_url || item.download_url || null;
                }

                if (!url) {
                    return;
                }

                const wrapper = document.createElement('div');
                wrapper.classList.add('border', 'rounded', 'p-2', 'mb-3');

                const link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = `Media ${index + 1}`;
                link.classList.add('d-block', 'mb-2');

                wrapper.appendChild(link);

                if (/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(url)) {
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = `Media ${index + 1}`;
                    img.classList.add('img-thumbnail');
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '200px';
                    wrapper.appendChild(img);
                }

                container.appendChild(wrapper);
            });

            if (!container.children.length) {
                container.innerHTML = '<p class="text-muted mb-0">No media available.</p>';
            }
        });
    });
</script>
