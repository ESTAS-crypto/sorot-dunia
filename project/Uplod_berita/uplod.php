<?php
$page_title = "Upload Berita";
require_once 'header.php';

// Pastikan user memiliki role yang tepat
if (!in_array($current_user['role'], ['admin', 'penulis'])) {
    header('Location: ../index.php');
    exit();
}

// Get categories from database
$categories_query = "SELECT name FROM categories ORDER BY name ASC";
$categories_result = mysqli_query($koneksi, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['name'];
}

if (empty($categories)) {
    $categories = ['politik', 'ekonomi', 'olahraga', 'teknologi', 'hiburan', 'kesehatan', 'pendidikan', 'kriminal', 'internasional', 'lainnya'];
}

// Check if editing existing article - USING SLUG
$editing = false;
$article_data = null;
$existing_tags = [];
$article_image = null;

if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $article_slug = trim($_GET['slug']);
    
    if (isAdmin()) {
        $article_query = "SELECT a.*, s.slug, c.name as category_name, 
                         GROUP_CONCAT(DISTINCT t.name) as tag_names,
                         i.id as image_id, i.filename as image_filename, 
                         i.url as image_url, i.is_external as image_is_external
                         FROM articles a 
                         LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
                         LEFT JOIN categories c ON a.category_id = c.category_id
                         LEFT JOIN article_tags at ON a.article_id = at.article_id
                         LEFT JOIN tags t ON at.tag_id = t.id
                         LEFT JOIN images i ON a.featured_image_id = i.id
                         WHERE s.slug = ?
                         GROUP BY a.article_id";
    } else {
        $article_query = "SELECT a.*, s.slug, c.name as category_name, 
                         GROUP_CONCAT(DISTINCT t.name) as tag_names,
                         i.id as image_id, i.filename as image_filename, 
                         i.url as image_url, i.is_external as image_is_external
                         FROM articles a 
                         LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
                         LEFT JOIN categories c ON a.category_id = c.category_id
                         LEFT JOIN article_tags at ON a.article_id = at.article_id
                         LEFT JOIN tags t ON at.tag_id = t.id
                         LEFT JOIN images i ON a.featured_image_id = i.id
                         WHERE s.slug = ? AND a.author_id = ?
                         GROUP BY a.article_id";
    }
    
    $stmt = mysqli_prepare($koneksi, $article_query);
    
    if (isAdmin()) {
        mysqli_stmt_bind_param($stmt, "s", $article_slug);
    } else {
        mysqli_stmt_bind_param($stmt, "si", $article_slug, $current_user['id']);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $article_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($article_data) {
        $editing = true;
        
        if (!empty($article_data['tag_names'])) {
            $existing_tags = explode(',', $article_data['tag_names']);
        }
        
        if ($article_data['image_id']) {
            $article_image = [
                'id' => $article_data['image_id'],
                'filename' => $article_data['image_filename'],
                'url' => $article_data['image_url'],
                'is_external' => $article_data['image_is_external']
            ];
        }
        
    } else {
        header('Location: manage.php?error=article_not_found');
        exit();
    }
}
?>

<!-- Summernote CSS Bootstrap 5 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.css" rel="stylesheet">

<style>
:root {
    --primary-color: #000000;
    --secondary-color: #333333;
    --accent-color: #666666;
    --light-gray: #f8f9fa;
    --medium-gray: #e9ecef;
    --border-color: #dee2e6;
    --white: #ffffff;
    --success-color: #28a745;
    --error-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --hover-color: #f1f3f4;
}

/* Upload page specific styles */
.upload-form-container {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    margin: 2rem 0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.upload-header {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-color);
}

.upload-header h2 {
    color: var(--primary-color);
    font-weight: 700;
    margin-bottom: 0.5rem;
    font-size: 2rem;
}

.upload-header p {
    color: var(--accent-color);
    font-size: 1.1rem;
    margin: 0;
}

/* NEW: Requirements Alert */
.requirements-alert {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.requirements-alert h5 {
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.requirements-alert h5 i {
    font-size: 1.5rem;
}

.requirements-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.requirements-list li {
    padding: 0.5rem 0;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.requirements-list li i {
    color: #ffd700;
    margin-top: 0.25rem;
    font-size: 1rem;
}

.requirements-list li strong {
    color: #ffd700;
}

.form-floating {
    margin-bottom: 1.5rem;
}

.char-counter {
    position: absolute;
    bottom: -20px;
    right: 10px;
    font-size: 0.875rem;
    color: var(--accent-color);
}

.slug-container {
    margin-bottom: 1.5rem;
    padding: 0.75rem;
    background: var(--light-gray);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.slug-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--accent-color);
    margin-bottom: 0.25rem;
}

.slug-display {
    font-family: monospace;
    color: var(--primary-color);
    font-weight: 500;
}

/* File upload area */
.file-upload-area {
    border: 3px dashed var(--border-color);
    border-radius: 15px;
    padding: 3rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--white);
    margin-bottom: 1.5rem;
    position: relative;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.file-upload-area * {
    pointer-events: none;
}

.file-upload-area:hover {
    border-color: var(--primary-color);
    background: rgba(0,0,0,0.02);
    transform: translateY(-2px);
}

.file-upload-area.dragover {
    border-color: #28a745 !important;
    background: rgba(40, 167, 69, 0.1) !important;
    border-style: solid !important;
    transform: scale(1.02);
    box-shadow: 0 8px 24px rgba(40, 167, 69, 0.2);
}

.file-upload-area.dragover::before {
    content: '📁 Drop file di sini';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    font-weight: bold;
    color: #28a745;
    background: white;
    padding: 1rem 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 10;
    pointer-events: none;
}

.file-upload-area.dragover > div {
    opacity: 0.3;
}

/* Required marker */
.required-marker {
    color: var(--error-color);
    font-weight: bold;
    margin-left: 0.25rem;
}

.upload-progress {
    display: none;
    margin-top: 1rem;
    padding: 1rem;
    background: var(--light-gray);
    border-radius: 8px;
}

.upload-progress .progress {
    height: 30px;
    border-radius: 8px;
    overflow: hidden;
    background: #e9ecef;
}

.upload-progress .progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    transition: width 0.3s ease;
}

.upload-status {
    display: none;
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 8px;
    border: 2px solid;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.upload-status.success {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.upload-status.error {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.uploaded-info {
    display: none;
    margin-top: 1rem;
    padding: 1.5rem;
    background: var(--light-gray);
    border-radius: 8px;
    border: 2px solid var(--border-color);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.uploaded-info img {
    border-radius: 8px;
    border: 2px solid var(--border-color);
    object-fit: cover;
}

.tag-display {
    min-height: 40px;
    padding: 0.75rem;
    background: var(--light-gray);
    border-radius: 8px;
    border: 1px solid var(--border-color);
    margin-top: 0.5rem;
}

.tag-item {
    display: inline-block;
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    margin: 0.25rem;
    font-size: 0.875rem;
}

.tag-remove {
    background: none;
    border: none;
    color: white;
    margin-left: 0.5rem;
    cursor: pointer;
}

/* Summernote styles */
.note-editor {
    border: 2px solid var(--border-color) !important;
    border-radius: 12px !important;
    margin-bottom: 1.5rem;
}

.note-toolbar {
    background: #f8f9fa !important;
    border-bottom: 2px solid var(--border-color) !important;
    border-radius: 12px 12px 0 0 !important;
    padding: 10px !important;
}

.note-btn {
    background: white !important;
    border: 1px solid #dee2e6 !important;
    border-radius: 4px !important;
    padding: 5px 10px !important;
    margin: 2px !important;
}

.note-btn:hover {
    background: var(--primary-color) !important;
    color: white !important;
    border-color: var(--primary-color) !important;
}

.note-editable {
    min-height: 400px !important;
    padding: 20px !important;
    font-size: 16px !important;
    line-height: 1.6 !important;
    background: white !important;
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    flex-wrap: wrap;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

.btn-draft {
    background: var(--accent-color);
    border-color: var(--accent-color);
    color: var(--white);
}

.btn-draft:hover {
    background: var(--secondary-color);
    border-color: var(--secondary-color);
    color: var(--white);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .file-upload-area {
        padding: 2rem 1rem;
        min-height: 150px;
    }
    
    .requirements-alert {
        padding: 1rem;
    }
    
    .requirements-list li {
        font-size: 0.9rem;
    }
}
</style>

<!-- Main Content -->
<div class="container" style="margin-top: 100px; margin-bottom: 50px;">
    <div class="upload-form-container">
        <div class="upload-header">
            <h2>
                <i class="fas fa-<?php echo $editing ? 'edit' : 'plus-circle'; ?> me-3"></i>
                <?php echo $editing ? 'Edit Berita' : 'Upload Berita Baru'; ?>
            </h2>
            <p>
                <?php if ($editing): ?>
                    Perbarui artikel "<?php echo htmlspecialchars($article_data['title']); ?>"
                <?php else: ?>
                    Bagikan berita terbaru untuk pembaca setia Sorot Dunia
                <?php endif; ?>
            </p>
        </div>

        <!-- NEW: Requirements Alert -->
        <div class="requirements-alert">
            <h5>
                <i class="fas fa-info-circle"></i>
                Syarat Publish Artikel
            </h5>
            <ul class="requirements-list">
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Gambar Berita:</strong> WAJIB upload gambar (JPG/PNG/WEBP, max 5MB)</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Judul:</strong> Maksimal 200 karakter</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Ringkasan:</strong> Maksimal 300 karakter</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Konten:</strong> Minimal 100 karakter</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Tag:</strong> Minimal 1 tag harus diisi</span>
                </li>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span><strong>Kategori:</strong> Pilih kategori yang sesuai</span>
                </li>
            </ul>
            <div class="mt-3" style="border-top: 1px solid rgba(255,255,255,0.3); padding-top: 1rem;">
                <small>
                    <i class="fas fa-lightbulb"></i>
                    <strong>Tips:</strong> Untuk menyimpan sebagai draft, syarat gambar & tag tidak wajib.
                </small>
            </div>
        </div>

        <div id="alertContainer"></div>

        <form id="uploadForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="featured_image_id" id="featuredImageId" 
                value="<?php echo $editing && $article_image ? $article_image['id'] : ''; ?>">
            <?php if ($editing): ?>
            <input type="hidden" name="article_id" value="<?php echo $article_data['article_id']; ?>">
            <input type="hidden" name="article_slug" value="<?php echo $article_data['slug']; ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <!-- Judul -->
                    <div class="form-floating position-relative">
                        <input type="text" class="form-control" id="title" name="title"
                            placeholder="Judul Berita" required maxlength="200"
                            value="<?php echo $editing ? htmlspecialchars($article_data['title']) : ''; ?>">
                        <label for="title">Judul Berita <span class="required-marker">*</span></label>
                        <div class="char-counter" id="titleCounter">0/200</div>
                    </div>

                    <!-- Slug Display -->
                    <div class="slug-container" id="slugContainer" 
                        style="<?php echo $editing && !empty($article_data['slug']) ? 'display: block;' : 'display: none;'; ?>">
                        <div class="slug-label">URL Slug</div>
                        <div class="slug-display" id="slugDisplay">
                            <?php echo $editing ? htmlspecialchars($article_data['slug']) : ''; ?>
                        </div>
                    </div>

                    <!-- Ringkasan -->
                    <div class="form-floating position-relative">
                        <textarea class="form-control" id="summary" name="summary"
                            placeholder="Ringkasan berita" style="height: 120px" required
                            maxlength="300"><?php echo $editing ? htmlspecialchars($article_data['meta_description']) : ''; ?></textarea>
                        <label for="summary">Ringkasan Berita <span class="required-marker">*</span></label>
                        <div class="char-counter" id="summaryCounter">0/300</div>
                    </div>

                    <!-- Konten dengan Summernote -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-edit me-2"></i>Konten Berita <span class="required-marker">*</span>
                            <small class="text-muted">(Min. 100 karakter untuk publish)</small>
                        </label>
                        <textarea class="form-control" id="content" name="content" required><?php echo $editing ? $article_data['content'] : ''; ?></textarea>
                        <div class="char-counter" id="contentCounter" style="position: relative; text-align: right; margin-top: 5px;">0 karakter</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Kategori -->
                    <div class="form-floating">
                        <select class="form-select" id="category" name="category" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo ($editing && isset($article_data['category_name']) && $cat === $article_data['category_name']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($cat)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="category">Kategori <span class="required-marker">*</span></label>
                    </div>

                    <!-- Tag -->
                    <div class="mb-3">
                        <label class="form-label">
                            Tambah Tag 
                            <span class="required-marker">*</span>
                            <small class="text-muted">(Min. 1 tag untuk publish)</small>
                        </label>
                        <input type="text" class="form-control" id="tagInput" 
                            placeholder="Ketik tag dan tekan Enter atau koma">
                        <div class="tag-display" id="tagDisplay">
                            <span class="text-muted">Tag akan muncul di sini</span>
                        </div>
                        <input type="hidden" name="tags" id="tagsHidden">
                    </div>

                    <!-- Status Info -->
                    <?php if ($editing): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Status:</strong> <?php echo ucfirst($article_data['article_status']); ?>
                    </div>
                    <?php elseif ($current_user['role'] === 'penulis'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Catatan:</strong> Artikel akan menunggu persetujuan admin.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upload Gambar -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="fas fa-image me-2"></i>Gambar Berita
                    <span class="required-marker">*</span>
                    <small class="text-muted">(WAJIB untuk publish artikel)</small>
                </label>
                
                <div class="file-upload-area" id="dropZone">
                    <input type="file" id="image" name="image" style="display: none;"
                        accept="image/jpeg,image/jpg,image/png,image/webp,image/gif">
                    
                    <div>
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h5>Klik atau Drag & Drop Gambar</h5>
                        <p class="text-muted">Format: JPG, PNG, GIF, WebP | Maksimal: 5MB<br>
                        Auto-convert ke WebP, resize max 1000px, compress max 300KB</p>
                        <div class="alert alert-warning mt-3" style="display: inline-block;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Wajib upload gambar untuk publish artikel!</strong>
                        </div>
                    </div>
                </div>

                <div class="upload-progress" id="uploadProgress">
                    <div class="progress">
                        <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
                    </div>
                </div>

                <div class="upload-status" id="uploadStatus"></div>

                <div class="uploaded-info" id="uploadedInfo" 
                    <?php echo ($editing && $article_image) ? 'style="display: block;"' : ''; ?>>
                    <div class="d-flex align-items-center">
                        <img id="uploadedThumbnail" width="60" height="60" class="rounded me-3"
                            <?php if ($editing && $article_image): ?>
                                src="<?php echo htmlspecialchars($article_image['url']); ?>"
                            <?php endif; ?>>
                        <div>
                            <h6 id="uploadedName" class="mb-1">
                                <?php echo $editing && $article_image ? htmlspecialchars($article_image['filename']) : ''; ?>
                            </h6>
                            <small id="uploadedDetails" class="text-muted">
                                <?php echo $editing && $article_image ? 'Gambar saat ini' : ''; ?>
                            </small>
                            <br>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="removeImage()">
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-undo me-2"></i>Reset
                </button>
                
                <button type="button" class="btn btn-draft" id="saveDraftBtn" onclick="saveDraft()">
                    <i class="fas fa-save me-2"></i>Simpan Draft
                    <small class="d-block" style="font-size: 0.75rem;">(Tanpa syarat wajib)</small>
                </button>
                
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-<?php echo $editing ? 'save' : 'upload'; ?> me-2"></i>
                    <?php 
                    if ($editing) {
                        echo 'Update Berita';
                    } else {
                        echo ($current_user['role'] === 'admin') ? 'Publish Berita' : 'Submit Berita';
                    }
                    ?>
                    <small class="d-block" style="font-size: 0.75rem;">(Cek syarat wajib)</small>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Load Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.js"></script>

<script>
let summernoteInitialized = false;

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    let tags = <?php echo json_encode($existing_tags); ?> || [];
    let uploadedImageId = <?php echo $editing && $article_image ? $article_image['id'] : 'null'; ?>;
    let isEditing = <?php echo $editing ? 'true' : 'false'; ?>;
    let isSubmitting = false;

    // Initialize Summernote
    initializeSummernote();
    
    // Initialize all functionality
    initSlugGeneration();
    initCharCounters();
    initTagManagement();
    initDragDrop();
    initFormSubmission();
    
    if (tags.length > 0) {
        updateTagDisplay();
    }
    
    updateAllCounters();

    function initializeSummernote() {
        if (typeof $ === 'undefined') {
            console.error('jQuery is not loaded');
            return;
        }
        
        const $editor = $('#content');
        
        if (!$editor.length) {
            console.error('Content textarea not found');
            return;
        }
        
        if ($editor.data('summernote')) {
            $editor.summernote('destroy');
        }
        
        $editor.summernote({
            height: 400,
            minHeight: 300,
            maxHeight: 600,
            placeholder: 'Tulis konten berita lengkap di sini... (Minimal 100 karakter untuk publish)',
            focus: false,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video', 'hr']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontNamesIgnoreCheck: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '36'],
            styleTags: ['p', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
            dialogsInBody: true,
            callbacks: {
                onChange: function(contents, $editable) {
                    updateContentCounter();
                },
                onPaste: function(e) {
                    setTimeout(function() {
                        updateContentCounter();
                    }, 100);
                },
                onInit: function() {
                    console.log('✓ Summernote initialized successfully');
                    summernoteInitialized = true;
                    updateContentCounter();
                    
                    setTimeout(function() {
                        $('.note-editor .dropdown-toggle').on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const $dropdown = $(this).next('.dropdown-menu');
                            $('.note-editor .dropdown-menu').not($dropdown).removeClass('show');
                            $dropdown.toggleClass('show');
                            
                            return false;
                        });
                        
                        $(document).on('click', function(e) {
                            if (!$(e.target).closest('.note-editor .dropdown').length) {
                                $('.note-editor .dropdown-menu').removeClass('show');
                            }
                        });
                    }, 500);
                }
            }
        });
    }

    function getEl(id) {
        try {
            return document.getElementById(id);
        } catch (e) {
            console.error('Element not found:', id);
            return null;
        }
    }

    function safeString(str, operation) {
        try {
            if (str === null || str === undefined) return '';
            if (typeof str !== 'string') str = String(str);
            
            switch(operation) {
                case 'trim': return str.trim();
                case 'toLowerCase': return str.toLowerCase();
                default: return str;
            }
        } catch (e) {
            console.error('String operation error:', e);
            return '';
        }
    }

    function initSlugGeneration() {
        const titleInput = getEl('title');
        const slugContainer = getEl('slugContainer');
        const slugDisplay = getEl('slugDisplay');
        
        if (!titleInput || !slugContainer || !slugDisplay) return;
        
        if (!isEditing) {
            titleInput.addEventListener('input', function() {
                const title = safeString(this.value, 'trim');
                
                if (title.length > 0) {
                    const slug = generateSlug(title);
                    slugDisplay.textContent = slug;
                    slugContainer.style.display = 'block';
                } else {
                    slugContainer.style.display = 'none';
                }
            });
        }
    }

    function generateSlug(text) {
        if (!text) return '';
        
        return safeString(text, 'toLowerCase')
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 100);
    }

    function initCharCounters() {
        ['title', 'summary'].forEach(id => {
            const element = getEl(id);
            const counter = getEl(id + 'Counter');
            
            if (!element || !counter) return;
            
            element.addEventListener('input', function() {
                updateCounter(element, counter);
            });
        });
    }

    function updateCounter(element, counter) {
        const length = element.value.length;
        const maxLength = element.getAttribute('maxlength') || 5000;
        counter.textContent = length + (maxLength !== '5000' ? '/' + maxLength : ' karakter');
        
        if (length > maxLength * 0.9) {
            counter.style.color = '#dc3545';
        } else if (length > maxLength * 0.7) {
            counter.style.color = '#ffc107';
        } else {
            counter.style.color = '#666';
        }
    }

    function updateContentCounter() {
        const contentCounter = getEl('contentCounter');
        if (!contentCounter) return;
        
        const content = $('#content').summernote('code');
        const textContent = $('<div>').html(content).text();
        const length = textContent.length;
        
        contentCounter.textContent = length + ' karakter';
        
        if (length < 100) {
            contentCounter.style.color = '#dc3545';
            contentCounter.textContent = length + ' karakter (Min. 100 untuk publish)';
        } else if (length > 5000) {
            contentCounter.style.color = '#dc3545';
        } else if (length > 3000) {
            contentCounter.style.color = '#ffc107';
        } else {
            contentCounter.style.color = '#28a745';
        }
    }

    function updateAllCounters() {
        ['title', 'summary'].forEach(id => {
            const element = getEl(id);
            const counter = getEl(id + 'Counter');
            if (element && counter) {
                updateCounter(element, counter);
            }
        });
        updateContentCounter();
    }

    function initTagManagement() {
        const tagInput = getEl('tagInput');
        
        if (!tagInput) return;
        
        tagInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addTag();
            }
        });

        tagInput.addEventListener('blur', function() {
            if (this.value.trim()) {
                addTag();
            }
        });

        function addTag() {
            const tagValue = safeString(tagInput.value, 'trim');
            
            if (!tagValue || tagValue.length === 0) return;
            
            const normalizedTag = safeString(tagValue, 'toLowerCase');
            
            if (tags.includes(normalizedTag)) {
                showAlert('warning', 'Tag sudah ada');
                tagInput.value = '';
                return;
            }
            
            if (tags.length >= 10) {
                showAlert('warning', 'Maksimal 10 tag');
                return;
            }
            
            if (normalizedTag.length > 50) {
                showAlert('warning', 'Tag maksimal 50 karakter');
                return;
            }
            
            tags.push(normalizedTag);
            updateTagDisplay();
            tagInput.value = '';
        }
    }

    function updateTagDisplay() {
        const tagDisplay = getEl('tagDisplay');
        const tagsHidden = getEl('tagsHidden');
        
        if (!tagDisplay || !tagsHidden) return;
        
        if (tags.length === 0) {
            tagDisplay.innerHTML = '<span class="text-muted">Tag akan muncul di sini</span>';
        } else {
            const tagHtml = tags.map((tag, index) => `
                <span class="tag-item">
                    ${tag}
                    <button type="button" class="tag-remove" onclick="removeTag(${index})">×</button>
                </span>
            `).join('');
            tagDisplay.innerHTML = tagHtml;
        }
        
        const validTags = tags.filter(tag => tag && typeof tag === 'string' && tag.length > 0);
        tagsHidden.value = validTags.join(',');
    }

    window.removeTag = function(index) {
        if (index >= 0 && index < tags.length) {
            tags.splice(index, 1);
            updateTagDisplay();
        }
    };

    // Drag and Drop
    function initDragDrop() {
        const dropZone = getEl('dropZone');
        const fileInput = getEl('image');
        
        if (!dropZone || !fileInput) {
            console.error('Drop zone or file input not found');
            return;
        }

        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });

        let dragCounter = 0;

        const events = ['dragenter', 'dragover', 'dragleave', 'drop'];
        
        events.forEach(eventName => {
            dropZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        dropZone.addEventListener('dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dragCounter++;
            dropZone.classList.add('dragover');
        }, false);

        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'copy';
            dropZone.classList.add('dragover');
        }, false);

        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dragCounter--;
            
            if (dragCounter === 0) {
                dropZone.classList.remove('dragover');
            }
        }, false);

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            dragCounter = 0;
            dropZone.classList.remove('dragover');
            
            const dt = e.dataTransfer;
            
            if (!dt) {
                showAlert('error', 'Gagal mengambil file');
                return;
            }
            
            const files = dt.files;
            
            if (files && files.length > 0) {
                handleFile(files[0]);
            }
        }, false);

        dropZone.addEventListener('click', function(e) {
            e.preventDefault();
            fileInput.click();
        }, false);

        function handleFile(file) {
            if (!file.type.startsWith('image/')) {
                showAlert('error', 'File harus berupa gambar');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showAlert('error', 'Ukuran file maksimal 5MB');
                return;
            }

            uploadFile(file);
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('csrf_token', getEl('uploadForm').querySelector('input[name="csrf_token"]').value);
            formData.append('action', 'pending');

            const uploadProgress = getEl('uploadProgress');
            const progressBar = getEl('progressBar');
            const uploadStatus = getEl('uploadStatus');
            
            uploadProgress.style.display = 'block';
            uploadStatus.style.display = 'none';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                }
            });

            xhr.onload = function() {
                uploadProgress.style.display = 'none';
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            handleUploadSuccess(response.data);
                        } else {
                            handleUploadError(response.message || 'Upload failed');
                        }
                    } catch (e) {
                        handleUploadError('Server response error');
                    }
                } else {
                    handleUploadError('Upload failed with status: ' + xhr.status);
                }
            };

            xhr.onerror = function() {
                uploadProgress.style.display = 'none';
                handleUploadError('Network error');
            };

            xhr.timeout = 60000;
            xhr.open('POST', '/project/ajax/upload_handler.php');
            xhr.send(formData);
        }

        function handleUploadSuccess(data) {
            uploadedImageId = data.image_id;
            getEl('featuredImageId').value = data.image_id;
            
            const uploadStatus = getEl('uploadStatus');
            uploadStatus.className = 'upload-status success';
            uploadStatus.textContent = '✓ Gambar berhasil diupload!';
            uploadStatus.style.display = 'block';

            getEl('uploadedThumbnail').src = data.url;
            getEl('uploadedName').textContent = data.filename;
            getEl('uploadedDetails').innerHTML = `${data.size} | ${data.dimensions}${data.optimized ? ' | <span class="text-success">Dioptimasi</span>' : ''}`;
            getEl('uploadedInfo').style.display = 'block';

            showAlert('success', 'Gambar berhasil diupload!');
            
            setTimeout(() => {
                uploadStatus.style.display = 'none';
            }, 3000);
        }

        function handleUploadError(message) {
            const uploadStatus = getEl('uploadStatus');
            uploadStatus.className = 'upload-status error';
            uploadStatus.textContent = '✗ Upload gagal: ' + message;
            uploadStatus.style.display = 'block';
            
            showAlert('error', message);
        }

        window.removeImage = function() {
            if (confirm('Hapus gambar?')) {
                uploadedImageId = null;
                getEl('featuredImageId').value = '';
                getEl('uploadStatus').style.display = 'none';
                getEl('uploadedInfo').style.display = 'none';
                showAlert('info', 'Gambar telah dihapus');
            }
        };
    }

    function initFormSubmission() {
        const form = getEl('uploadForm');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitArticle('publish');
        });
    }

    window.saveDraft = function() {
        submitArticle('draft');
    };

    function submitArticle(action) {
        if (isSubmitting) return;
        if (!validateForm(action)) return;
        
        isSubmitting = true;
        
        const submitBtn = getEl('submitBtn');
        const draftBtn = getEl('saveDraftBtn');
        const targetBtn = action === 'draft' ? draftBtn : submitBtn;
        const originalText = targetBtn.innerHTML;
        
        targetBtn.disabled = true;
        draftBtn.disabled = true;
        submitBtn.disabled = true;
        
        targetBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + 
            (action === 'draft' ? 'Menyimpan...' : 'Memproses...');

        const formData = new FormData();
        formData.append('csrf_token', getEl('uploadForm').querySelector('input[name="csrf_token"]').value);
        formData.append('title', safeString(getEl('title').value, 'trim'));
        formData.append('summary', safeString(getEl('summary').value, 'trim'));
        formData.append('content', $('#content').summernote('code'));
        formData.append('category', getEl('category').value);
        formData.append('action', action);
        
        if (isEditing) {
            formData.append('article_id', getEl('uploadForm').querySelector('input[name="article_id"]').value);
        }
        
        const validTags = tags.filter(tag => tag && typeof tag === 'string' && tag.length > 0);
        formData.append('tags', validTags.join(','));
        formData.append('featured_image_id', uploadedImageId || '');

        fetch('/project/ajax/submit_article.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showAlert('success', data.message);
                    
                    if (action === 'publish' && !isEditing) {
                        setTimeout(() => window.location.href = 'manage.php', 2000);
                    } else if (action === 'draft') {
                        setTimeout(() => window.location.reload(), 1000);
                    } else if (isEditing) {
                        setTimeout(() => window.location.href = 'manage.php', 2000);
                    }
                } else {
                    showAlert('error', data.message);
                    isSubmitting = false;
                }
            } catch (e) {
                showAlert('error', 'Server response error');
                isSubmitting = false;
            }
        })
        .catch(error => {
            showAlert('error', 'Network error occurred');
            isSubmitting = false;
        })
        .finally(() => {
            setTimeout(() => {
                if (isSubmitting) {
                    submitBtn.disabled = false;
                    draftBtn.disabled = false;
                    targetBtn.innerHTML = originalText;
                }
            }, 500);
        });
    }

    function validateForm(action) {
        let isValid = true;
        let errors = [];
        
        const requiredFields = ['title', 'summary', 'category'];
        requiredFields.forEach(fieldId => {
            const field = getEl(fieldId);
            const value = safeString(field.value, 'trim');
            
            if (!value || value.length === 0) {
                field.classList.add('is-invalid');
                errors.push(`${field.labels[0]?.textContent || fieldId} wajib diisi`);
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            }
        });

        const content = $('#content').summernote('code');
        const textContent = $('<div>').html(content).text().trim();
        
        if (!textContent || textContent.length === 0) {
            $('#content').next('.note-editor').addClass('is-invalid');
            errors.push('Konten berita wajib diisi');
            isValid = false;
        } else {
            $('#content').next('.note-editor').removeClass('is-invalid').addClass('is-valid');
        }

        // PUBLISH VALIDATION
        if (action === 'publish') {
            // Check image
            if (!uploadedImageId || uploadedImageId <= 0) {
                errors.push('⚠️ GAMBAR BERITA WAJIB DIUPLOAD untuk publish artikel!');
                isValid = false;
                
                // Highlight upload area
                const dropZone = getEl('dropZone');
                dropZone.style.borderColor = '#dc3545';
                dropZone.style.background = '#f8d7da';
                setTimeout(() => {
                    dropZone.style.borderColor = '';
                    dropZone.style.background = '';
                }, 3000);
            }
            
            // Check tags
            if (tags.length === 0) {
                errors.push('⚠️ TAG ARTIKEL WAJIB DIISI minimal 1 tag untuk publish!');
                isValid = false;
                
                const tagInput = getEl('tagInput');
                tagInput.classList.add('is-invalid');
                setTimeout(() => {
                    tagInput.classList.remove('is-invalid');
                }, 3000);
            }
            
            // Check content length
            if (textContent.length < 100) {
                errors.push(`⚠️ KONTEN ARTIKEL TERLALU PENDEK! Minimal 100 karakter untuk publish (saat ini: ${textContent.length} karakter)`);
                isValid = false;
            }
        }

        if (!isValid) {
            const errorMessage = errors.join('<br>');
            showAlert('error', errorMessage);
            
            // Scroll to top to see error
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        return isValid;
    }

    function showAlert(type, message) {
        const alertContainer = getEl('alertContainer');
        if (!alertContainer) return;
        
        const alertClass = type === 'success' ? 'alert-success' : 
                         (type === 'warning' ? 'alert-warning' : 
                         (type === 'info' ? 'alert-info' : 'alert-danger'));
        const icon = type === 'success' ? 'check-circle' : 
                   (type === 'warning' ? 'exclamation-triangle' : 
                   (type === 'info' ? 'info-circle' : 'exclamation-triangle'));
        
        alertContainer.innerHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show">
                <i class="fas fa-${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) alert.remove();
        }, 8000);
    }

    window.resetForm = function() {
        if (confirm('Reset form? Semua perubahan akan hilang.')) {
            getEl('uploadForm').reset();
            $('#content').summernote('code', '');
            tags = [];
            getEl('tagDisplay').innerHTML = '<span class="text-muted">Tag akan muncul di sini</span>';
            getEl('tagsHidden').value = '';
            uploadedImageId = null;
            getEl('featuredImageId').value = '';
            getEl('slugContainer').style.display = 'none';
            getEl('uploadStatus').style.display = 'none';
            getEl('uploadedInfo').style.display = 'none';
            updateAllCounters();
            showAlert('info', 'Form telah direset');
        }
    };

    console.log('✓ Upload page initialized with mandatory validations');
});
</script>

<?php require_once 'footer.php'; ?>