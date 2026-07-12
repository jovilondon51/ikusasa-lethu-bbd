<?php 
$scriptPath = $_SERVER['PHP_SELF'] ?? '';
$basePath = (strpos($scriptPath, '/admin/') !== false || strpos($scriptPath, '/learner/') !== false) ? '../' : '';
?>
<?php if ($currentUser): ?>
    </main>
<?php else: ?>
    </main>
<?php endif; ?>
<script src="<?php echo $basePath; ?>assets/js/main.js"></script>
</body>
</html>