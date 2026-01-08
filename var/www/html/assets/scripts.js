document.addEventListener('DOMContentLoaded', function() {
    // Navbar toggle
    const toggler = document.querySelector('.navbar-toggler');
    const menu = document.querySelector('.navbar-menu');
    if (toggler && menu) {
        toggler.addEventListener('click', () => {
            menu.classList.toggle('active');
        });
    }

    // Upload Form Logic (index.php)
    // We select by enctype to specifically target the file upload form
    const uploadForm = document.querySelector('form[enctype="multipart/form-data"]');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            const btn = uploadForm.querySelector('button');
            const fileLabel = uploadForm.querySelector('label');
            const fileInput = uploadForm.querySelector('input[name="file"]');

            if (fileInput && fileInput.files.length > 0) {
                // Get max size from data attribute
                const maxSize = parseInt(fileInput.getAttribute('data-max-size'), 10);
                
                if (!isNaN(maxSize) && fileInput.files[0].size > maxSize) {
                    e.preventDefault();
                    alert('File is too large. Maximum size is ' + (maxSize / 1024 / 1024) + 'MiB.');
                    return;
                }
            }

            btn.disabled = true;
            btn.textContent = 'UPLOADING...';
            btn.classList.add('upload-animation');
            if (fileInput) {
                fileInput.style.pointerEvents = 'none';
                fileInput.style.opacity = '0.5';
                fileInput.style.display = 'none';
                fileLabel.style.display = 'none';
            }
        });
    }

    // File Search Logic (index.php)
    const searchInput = document.getElementById('fileSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('table tbody tr');

            rows.forEach(row => {
                // Filter only if 3 or more characters, otherwise show all
                if (filter.length >= 3 && !row.textContent.toLowerCase().includes(filter)) {
                    row.style.display = "none";
                } else {
                    row.style.display = "";
                }
            });
        });
    }

    // Message Character Count Logic (messages.php)
    const messageInput = document.querySelector('textarea[name="message"]');
    const charCountDisplay = document.getElementById('char-count');

    if (messageInput && charCountDisplay) {
        const maxLength = messageInput.getAttribute('maxlength');
        messageInput.addEventListener('input', function() {
            charCountDisplay.textContent = `${messageInput.value.length} / ${maxLength}`;
        });
    }
});