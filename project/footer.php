<?php
$baseUrl = 'https://inievan.my.id/project';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Get site info from settings
$siteInfo = getSiteInfo();
?>
<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <!-- Brand and Description -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="mb-3">
                    <a class="brand-logo" href="<?php echo $baseUrl; ?>">
                        <img src="<?php echo $baseUrl; ?>/img/NewLogo.webp"
                            alt="<?php echo htmlspecialchars($siteInfo['name']); ?> Logo">
                    </a>
                </div>
                <p><?php echo htmlspecialchars($siteInfo['description']); ?></p>
            </div>

            <!-- Categories -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <h5>Kategori</h5>
                <ul class="list-unstyled">
                    <?php
                    // Load categories from database
                    $categories_query = "SELECT name FROM categories ORDER BY name LIMIT 5";
                    $categories_result = mysqli_query($koneksi, $categories_query);
                    
                    if ($categories_result && mysqli_num_rows($categories_result) > 0) {
                        while ($category = mysqli_fetch_assoc($categories_result)) {
                            echo '<li><a href="#">' . htmlspecialchars($category['name']) . '</a></li>';
                        }
                    } else {
                        // Default categories if none in database
                        echo '<li><a href="#">Politik</a></li>';
                        echo '<li><a href="#">Ekonomi</a></li>';
                        echo '<li><a href="#">Olahraga</a></li>';
                        echo '<li><a href="#">Teknologi</a></li>';
                        echo '<li><a href="#">Hiburan</a></li>';
                    }
                    ?>
                </ul>
            </div>

            <!-- Pages -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <h5>Halaman</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo $baseUrl; ?>">Beranda</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/berita.php">Berita</a></li>
                    <li><a href="#">Tentang Kami</a></li>
                    <li><a href="#">Kontak</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                </ul>
            </div>

            <!-- Contact Information -->
            <div class="col-lg-4 col-md-6 mb-4">
                <h5>Kontak</h5>
                <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($siteInfo['admin_email']); ?></p>
                <p><i class="fas fa-phone me-2"></i>+62 895-3858-90629</p>
                <p><i class="fas fa-map-marker-alt me-2"></i>Surabaya, Indonesia</p>

                <!-- Social Media Icons -->
                <div class="social-icons justify-content-start">
                    <a href="https://github.com/ESTAS-crypto" target="_blank" title="GitHub">
                        <i class="bi bi-github"></i>
                    </a>
                    <a href="https://www.instagram.com/evanatharasya.x/" target="_blank" title="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="https://x.com/EAtharasya" target="_blank" title="Twitter/X">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>&copy; 2025 <?php echo htmlspecialchars($siteInfo['name']); ?>. All rights reserved.</p>
            <p>Created by SMKN 2 SURABAYA - @Evan Atharasya</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>