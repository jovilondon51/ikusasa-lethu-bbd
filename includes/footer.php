<?php 
$scriptPath = $_SERVER['PHP_SELF'] ?? '';
$basePath = (strpos($scriptPath, '/admin/') !== false || strpos($scriptPath, '/learner/') !== false) ? '../' : '';
?>
<?php if ($currentUser): ?>
    </main>
<?php else: ?>
    </main>
<?php endif; ?>
<script src="<?php echo $basePath; ?>assets/js/passwords.js?v=<?php echo substr(hash_file('sha256', __DIR__ . '/../assets/js/passwords.js'), 0, 12); ?>"></script>
<script src="<?php echo $basePath; ?>assets/js/main.js?v=<?php echo substr(hash_file('sha256', __DIR__ . '/../assets/js/main.js'), 0, 12); ?>"></script>
</body>
</html>
