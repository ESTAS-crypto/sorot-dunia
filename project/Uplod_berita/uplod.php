<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Berita - SOROT DUNIA</title>
    <link rel="icon" href="../project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    :root {
        --primary-color: #000000;
        --secondary-color: #333333;
        --accent-color: #666666;
        --dark-color: #000000;
        --light-color: #ffffff;
        --border-color: #e0e0e0;
        --hover-color: #f5f5f5;
    }

    body {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .navbar {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border-bottom: 1px solid var(--border-color);
        padding: 0.75rem 0;
        transition: transform 0.3s ease-in-out;
    }

    .navbar-brand {
        font-weight: 700;
        color: var(--primary-color) !important;
        font-size: 1.5rem;
        margin-right: 3rem;
    }

    .nav-link {
        color: var(--dark-color) !important;
        font-weight: 500;
        transition: all 0.3s ease;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        margin: 0 0.3rem;
    }

    .nav-link:hover {
        color: var(--accent-color) !important;
        background: var(--hover-color);
    }

    .nav-link.active {
        color: var(--primary-color) !important;
        font-weight: 600;
        background: rgba(0, 0, 0, 0.05);
    }

    .brand-logo {
        display: flex;
        align-items: center;
        text-decoration: none;
    }

    .brand-logo img {
        height: 40px;
        width: auto;
        margin-right: 0.5rem;
    }

    .navbar-nav.me-auto {
        margin-left: 1rem;
    }

    .navbar-nav .nav-item {
        margin: 0 0.2rem;
    }

    .upload-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .upload-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .upload-header h2 {
        color: var(--dark-color);
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-size: 2rem;
    }

    .upload-header p {
        color: var(--secondary-color);
        font-size: 1.1rem;
    }

    .form-control,
    .form-select {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--light-color);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1);
        background: var(--light-color);
    }

    .form-label {
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 0.5rem;
    }

    .btn-primary {
        background: var(--primary-color);
        border: none;
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        color: var(--light-color);
    }

    .btn-primary:hover {
        background: var(--secondary-color);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-secondary {
        background: var(--light-color);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
        transition: all 0.3s ease;
        color: var(--dark-color);
    }

    .btn-secondary:hover {
        background: var(--hover-color);
        border-color: var(--secondary-color);
    }

    .file-upload-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
        width: 100%;
    }

    .file-upload-input {
        position: absolute;
        left: -9999px;
    }

    .file-upload-label {
        cursor: pointer;
        border: 2px dashed var(--border-color);
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        background: var(--light-color);
        display: block;
        width: 100%;
    }

    .file-upload-label:hover {
        border-color: var(--primary-color);
        background: var(--hover-color);
    }

    .file-upload-icon {
        font-size: 3rem;
        color: var(--accent-color);
        margin-bottom: 1rem;
    }

    .preview-container {
        margin-top: 1rem;
        display: none;
        text-align: center;
    }

    .preview-image {
        max-width: 100%;
        max-height: 200px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border-color);
    }

    .category-select {
        background: var(--light-color);
    }

    .form-floating {
        margin-bottom: 1rem;
    }

    .form-floating>.form-control {
        height: calc(3.5rem + 2px);
        padding: 1rem 0.75rem;
    }

    .form-floating>.form-select {
        height: calc(3.5rem + 2px);
    }

    .form-floating>label {
        padding: 1rem 0.75rem;
    }

    .success-message {
        background: var(--primary-color);
        color: var(--light-color);
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: none;
        border: 1px solid var(--secondary-color);
    }

    .required {
        color: var(--primary-color);
    }

    .char-counter {
        font-size: 0.875rem;
        color: var(--accent-color);
        margin-top: 0.25rem;
        text-align: right;
    }

    /* Mobile Responsive - FIXED */
    @media (max-width: 767px) {
        .navbar-brand {
            margin-right: 0;
            font-size: 1.3rem;
        }

        .navbar-nav.me-auto {
            margin-left: 0;
            width: 100%;
            text-align: center;
        }

        .navbar-nav {
            width: 100%;
            justify-content: center;
        }

        .navbar-nav .nav-item {
            margin: 0.2rem 0.5rem;
        }

        .nav-link {
            margin: 0;
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
        }

        .brand-logo {
            justify-content: center;
            margin-bottom: 0.5rem;
        }

        .brand-logo img {
            height: 30px;
        }

        .upload-container {
            margin: 1rem 0.5rem;
            padding: 1.5rem;
        }

        .upload-header h2 {
            font-size: 1.5rem;
        }

        .upload-header p {
            font-size: 0.95rem;
        }

        /* FIX: Floating labels untuk mobile */
        .form-floating>.form-control,
        .form-floating>.form-select {
            height: calc(3.2rem + 2px);
            padding: 1rem 0.75rem;
            font-size: 0.95rem;
        }

        .form-floating>label {
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
            /* Lebih kecil untuk mobile */
            transform-origin: 0 0;
            /* Pastikan scaling dari pojok kiri */
        }

        /* FIX: Kategori dropdown khusus mobile */
        .form-floating>.form-select {
            padding-top: 1.5rem;
            padding-bottom: 0.8rem;
        }

        .form-floating>.form-select~label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
        }

        .form-floating>.form-select:focus~label,
        .form-floating>.form-select:not(:placeholder-shown)~label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
        }

        .file-upload-label {
            padding: 1rem;
        }

        .file-upload-icon {
            font-size: 1.8rem;
        }

        .btn-primary,
        .btn-secondary {
            padding: 10px 20px;
            font-size: 0.95rem;
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .d-grid.gap-2.d-md-flex {
            flex-direction: column;
        }

        /* FIX: Navbar collapse untuk mobile */
        .navbar-collapse {
            text-align: center;
        }

        .navbar-collapse.show {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            margin-top: 1rem;
            padding: 1rem;
        }

        /* FIX: Responsive grid untuk mobile */
        .col-md-8,
        .col-md-4 {
            margin-bottom: 1rem;
        }
    }

    /* Medium screens */
    @media (min-width: 768px) and (max-width: 991px) {
        .container {
            max-width: 95%;
        }

        .navbar-brand {
            margin-right: 2rem;
        }

        .navbar-nav.me-auto {
            margin-left: 0.6rem;
        }

        .upload-container {
            padding: 2rem;
            margin: 1rem;
        }

        .upload-header h2 {
            font-size: 1.6rem;
        }

        .upload-header p {
            font-size: 1rem;
        }

        .form-floating>.form-control,
        .form-floating>.form-select {
            height: calc(3rem + 2px);
            padding: 0.8rem 0.75rem;
        }

        .form-floating>label {
            padding: 0.8rem 0.75rem;
        }

        .brand-logo img {
            height: 35px;
        }
    }

    /* Large screens */
    @media (min-width: 992px) {
        .container {
            max-width: 90%;
        }

        .navbar-brand {
            margin-right: 2.5rem;
        }

        .navbar-nav.me-auto {
            margin-left: 0.8rem;
        }
    }

    /* Extra large screens */
    @media (min-width: 1367px) {
        .container {
            max-width: 1200px;
        }

        .navbar-brand {
            margin-right: 4rem;
        }

        .navbar-nav.me-auto {
            margin-left: 1.5rem;
        }

        .upload-container {
            padding: 3rem;
        }

        .upload-header h2 {
            font-size: 2.2rem;
        }

        .upload-header p {
            font-size: 1.2rem;
        }
    }

    /* Smooth scroll behavior */
    html {
        scroll-behavior: smooth;
    }

    /* Loading animation */
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Form validation styles */
    .was-validated .form-control:valid {
        border-color: #28a745;
    }

    .was-validated .form-control:invalid {
        border-color: #dc3545;
    }

    .was-validated .form-select:valid {
        border-color: #28a745;
    }

    .was-validated .form-select:invalid {
        border-color: #dc3545;
    }

    /* Better button states */
    .btn-primary:disabled {
        background: var(--accent-color);
        border-color: var(--accent-color);
        cursor: not-allowed;
    }

    .btn-secondary:disabled {
        background: var(--border-color);
        border-color: var(--border-color);
        color: var(--accent-color);
        cursor: not-allowed;
    }

    /* Better alignment for navbar items */
    .navbar-collapse {
        justify-content: space-between;
    }

    .navbar-nav {
        align-items: center;
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="brand-logo" href="#">
                <img src="/project/img/Logo.webp" alt="Hot News Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/project/index.php">
                            <i class="fas fa-home me-1"></i>Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="upload.php">
                            <i class="fas fa-plus-circle me-1"></i>Upload Berita
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage.php">
                            <i class="fas fa-cog me-1"></i>Kelola Berita
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/project/logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 90px;">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="upload-container">
                    <div class="upload-header">
                        <h2><i class="fas fa-plus-circle me-2"></i>Upload Berita Baru</h2>
                        <p>Bagikan berita terbaru untuk pembaca setia Sorot dunia</p>
                    </div>

                    <div class="success-message" id="successMessage">
                        <i class="fas fa-check-circle me-2"></i>Berita berhasil diupload!
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data" novalidate>
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Judul Berita -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="title" name="title"
                                        placeholder="Judul Berita" required maxlength="200">
                                    <label for="title">Judul Berita <span class="required">*</span></label>
                                    <div class="char-counter" id="titleCounter">0/200 karakter</div>
                                </div>

                                <!-- Ringkasan -->
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="summary" name="summary"
                                        placeholder="Ringkasan berita" style="height: 100px" required
                                        maxlength="300"></textarea>
                                    <label for="summary">Ringkasan Berita <span class="required">*</span></label>
                                    <div class="char-counter" id="summaryCounter">0/300 karakter</div>
                                </div>

                                <!-- Konten Berita -->
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="content" name="content"
                                        placeholder="Konten lengkap berita" style="height: 200px" required
                                        maxlength="5000"></textarea>
                                    <label for="content">Konten Berita <span class="required">*</span></label>
                                    <div class="char-counter" id="contentCounter">0/5000 karakter</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <!-- Kategori -->
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Pilih Kategori</option>
                                        <option value="politik">Politik</option>
                                        <option value="ekonomi">Ekonomi</option>
                                        <option value="olahraga">Olahraga</option>
                                        <option value="teknologi">Teknologi</option>
                                        <option value="hiburan">Hiburan</option>
                                        <option value="kesehatan">Kesehatan</option>
                                        <option value="pendidikan">Pendidikan</option>
                                        <option value="kriminal">Kriminal</option>
                                        <option value="internasional">Internasional</option>
                                        <option value="lainnya">Lainnya</option>
                                    </select>
                                    <label for="category">Kategori <span class="required">*</span></label>
                                </div>
                                <!-- Tags -->
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="tags" name="tags"
                                        placeholder="Tags (pisahkan dengan koma)" maxlength="200">
                                    <label for="tags">Tags</label>
                                    <small class="text-muted">Contoh: breaking news, jakarta, politik</small>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Gambar -->
                        <div class="mb-4">
                            <label class="form-label">Gambar Berita</label>
                            <div class="file-upload-wrapper">
                                <input type="file" class="file-upload-input" id="image" name="image" accept="image/*">
                                <label for="image" class="file-upload-label">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <h5>Klik untuk upload gambar</h5>
                                    <p class="text-muted">Atau drag & drop file gambar di sini</p>
                                    <small class="text-muted">Format: JPG, PNG, GIF (Max: 5MB)</small>
                                </label>
                            </div>
                            <div class="preview-container" id="previewContainer">
                                <img class="preview-image" id="previewImage" alt="Preview">
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-secondary me-md-2" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Berita
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Character counter function
    function updateCharCounter(elementId, counterId, maxLength) {
        const element = document.getElementById(elementId);
        const counter = document.getElementById(counterId);

        element.addEventListener('input', function() {
            const currentLength = this.value.length;
            counter.textContent = `${currentLength}/${maxLength} karakter`;

            if (currentLength > maxLength * 0.9) {
                counter.style.color = 'var(--primary-color)';
            } else {
                counter.style.color = 'var(--accent-color)';
            }
        });
    }

    // Initialize character counters
    updateCharCounter('title', 'titleCounter', 200);
    updateCharCounter('summary', 'summaryCounter', 300);
    updateCharCounter('content', 'contentCounter', 5000);

    // Preview gambar
    document.getElementById('image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file size
            if (file.size > 5 * 1024 * 1024) { // 5MB
                alert('Ukuran file terlalu besar! Maksimal 5MB.');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
                document.getElementById('previewContainer').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Drag & Drop functionality
    const fileLabel = document.querySelector('.file-upload-label');

    fileLabel.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--primary-color)';
        this.style.backgroundColor = 'var(--hover-color)';
    });

    fileLabel.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--border-color)';
        this.style.backgroundColor = 'var(--light-color)';
    });

    fileLabel.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--border-color)';
        this.style.backgroundColor = 'var(--light-color)';

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('image').files = files;
            // Trigger change event
            const event = new Event('change');
            document.getElementById('image').dispatchEvent(event);
        }
    });

    // Form submission
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Validasi form
        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            return;
        }

        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const resetBtn = this.querySelector('button[type="button"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.innerHTML = '<span class="loading-spinner"></span> Uploading...';
        submitBtn.disabled = true;
        resetBtn.disabled = true;

        // Simulate upload process
        setTimeout(() => {
            document.getElementById('successMessage').style.display = 'block';
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            resetBtn.disabled = false;

            // Scroll to success message
            document.getElementById('successMessage').scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });

            // Reset form after success
            setTimeout(() => {
                resetForm();
                document.getElementById('successMessage').style.display = 'none';
            }, 3000);
        }, 2000);
    });

    // Reset form function
    function resetForm() {
        document.getElementById('uploadForm').reset();
        document.getElementById('previewContainer').style.display = 'none';
        document.getElementById('uploadForm').classList.remove('was-validated');

        // Reset character counters
        document.getElementById('titleCounter').textContent = '0/200 karakter';
        document.getElementById('summaryCounter').textContent = '0/300 karakter';
        document.getElementById('contentCounter').textContent = '0/5000 karakter';

        // Reset counter colors
        document.getElementById('titleCounter').style.color = 'var(--accent-color)';
        document.getElementById('summaryCounter').style.color = 'var(--accent-color)';
        document.getElementById('contentCounter').style.color = 'var(--accent-color)';
    }

    // Auto-resize textareas
    function autoResizeTextarea(element) {
        element.style.height = 'auto';
        element.style.height = element.scrollHeight + 'px';
    }

    // Apply auto-resize to textareas
    document.getElementById('summary').addEventListener('input', function() {
        autoResizeTextarea(this);
    });

    document.getElementById('content').addEventListener('input', function() {
        autoResizeTextarea(this);
    });

    // Smooth scroll for navbar links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Show/hide navbar on scroll
    let lastScrollTop = 0;
    const navbar = document.querySelector('.navbar');

    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            navbar.style.transform = 'translateY(0)';
        }

        lastScrollTop = scrollTop;
    });
    </script>
</body>

</html>