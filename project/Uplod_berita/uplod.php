<?php
$page_title = "Upload Berita";
require_once 'header.php';

// Get categories from database
$categories_query = "SELECT name FROM categories ORDER BY name ASC";
$categories_result = mysqli_query($koneksi, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['name'];
}

// If no categories found, add default ones
if (empty($categories)) {
    $categories = ['politik', 'ekonomi', 'olahraga', 'teknologi', 'hiburan', 'kesehatan', 'pendidikan', 'kriminal', 'internasional', 'lainnya'];
}
?>

<!-- Main Content -->
<div class="container" style="margin-top: 100px; margin-bottom: 50px;">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="upload-container">
                <div class="upload-header">
                    <h2><i class="fas fa-plus-circle me-3"></i>Upload Berita Baru</h2>
                    <p>Bagikan berita terbaru untuk pembaca setia Sorot Dunia</p>
                </div>

                <!-- Alert akan ditampilkan di sini oleh JavaScript -->

                <form id="uploadForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="row">
                        <div class="col-md-8">
                            <!-- Judul Berita -->
                            <div class="form-floating">
                                <input type="text" class="form-control" id="title" name="title"
                                    placeholder="Judul Berita" required maxlength="200">
                                <label for="title">Judul Berita <span class="required">*</span></label>
                                <div class="char-counter" id="titleCounter">0/200</div>
                                <div class="invalid-feedback">
                                    Judul berita harus diisi dan maksimal 200 karakter.
                                </div>
                            </div>

                            <!-- Slug Display -->
                            <div class="slug-container" id="slugContainer" style="display: none;">
                                <div class="slug-label">URL Slug (Preview)</div>
                                <div class="slug-display" id="slugDisplay"></div>
                            </div>

                            <!-- Ringkasan -->
                            <div class="form-floating">
                                <textarea class="form-control" id="summary" name="summary"
                                    placeholder="Ringkasan berita" style="height: 120px" required
                                    maxlength="300"></textarea>
                                <label for="summary">Ringkasan Berita <span class="required">*</span></label>
                                <div class="char-counter" id="summaryCounter">0/300</div>
                                <div class="invalid-feedback">
                                    Ringkasan berita harus diisi dan maksimal 300 karakter.
                                </div>
                            </div>

                            <!-- Konten Berita -->
                            <div class="form-floating">
                                <textarea class="form-control" id="content" name="content"
                                    placeholder="Konten lengkap berita" style="height: 250px" required
                                    maxlength="5000"></textarea>
                                <label for="content">Konten Berita <span class="required">*</span></label>
                                <div class="char-counter" id="contentCounter">0/5000</div>
                                <div class="invalid-feedback">
                                    Konten berita harus diisi dan maksimal 5000 karakter.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <!-- Kategori -->
                            <div class="form-floating">
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo ucfirst(htmlspecialchars($category)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="category">Kategori <span class="required">*</span></label>
                                <div class="invalid-feedback">
                                    Pilih kategori berita yang sesuai.
                                </div>
                            </div>

                            <!-- Tag Input with Enhanced UI -->
                            <div class="tag-input-container">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="tagInput"
                                        placeholder="Ketik tag dan tekan Enter">
                                    <label for="tagInput">Tambah Tag
                                        <span class="tooltip-custom" data-tooltip="Ketik tag dan tekan Enter atau koma">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </label>
                                </div>

                                <!-- Tag Display Area -->
                                <div class="tag-display" id="tagDisplay">
                                    <div class="text-muted" id="tagPlaceholder">
                                        <i class="fas fa-tag me-2"></i>Tag akan muncul di sini
                                    </div>
                                </div>

                                <!-- Hidden input for tags -->
                                <input type="hidden" name="tags" id="tagsHidden">

                                <!-- Tag Suggestions -->
                                <div class="tag-suggestions" id="tagSuggestions"></div>
                            </div>

                            <!-- Status Info for Penulis -->
                            <?php if ($current_user['role'] === 'penulis'): ?>
                            <div class="alert alert-info alert-custom">
                                <i class="fas fa-info-circle me-2"></i>
                                <div>
                                    <strong>Catatan:</strong> Artikel Anda akan masuk ke status "pending" dan menunggu
                                    persetujuan admin sebelum dipublikasikan.
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upload Gambar -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-image me-2"></i>Gambar Berita
                            <span class="tooltip-custom"
                                data-tooltip="Gambar akan otomatis dioptimalkan ke format WebP dengan ukuran maksimal 300KB">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        </label>
                        <div class="file-upload-wrapper">
                            <input type="file" class="file-upload-input" id="image" name="image"
                                accept="image/jpeg,image/jpg,image/png,image/webp,image/gif">
                            <label for="image" class="file-upload-label">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h5 class="file-upload-text">Klik untuk upload gambar</h5>
                                <p class="file-upload-subtext">Atau drag & drop file gambar di sini</p>
                                <div class="file-upload-info">
                                    <strong>Format yang didukung:</strong> JPG, PNG, GIF, WebP<br>
                                    <strong>Ukuran maksimal:</strong> 5MB (akan dioptimalkan ke 300KB WebP)
                                </div>
                            </label>
                        </div>

                        <!-- Preview Container -->
                        <div class="preview-container" id="previewContainer">
                            <img class="preview-image" id="previewImage" alt="Preview">
                            <button type="button" class="remove-preview" onclick="removePreview()" title="Hapus gambar">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="preview-info" id="previewInfo"></div>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div class="upload-progress" id="uploadProgress">
                        <label class="form-label fw-bold">
                            <i class="fas fa-upload me-2"></i>Progress Upload
                        </label>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0"
                                aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-3 d-md-flex justify-content-md-end pt-4 border-top">
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset Form
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>
                            <?php echo ($current_user['role'] === 'admin') ? 'Publish Berita' : 'Upload Berita'; ?>
                        </button>
                    </div>

                    <!-- Keyboard Shortcuts Info -->
                    <div class="mt-4 text-center">
                        <small class="text-muted">
                            <i class="fas fa-keyboard me-1"></i>
                            Keyboard shortcuts: <kbd>Ctrl+Enter</kbd> untuk submit, <kbd>Esc</kbd> untuk reset
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>