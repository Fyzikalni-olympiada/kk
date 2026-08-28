<?php
/* Výsledková listina: tělo už obsahuje tabulku převedenou při migraci
   (dřív ji presenter Results skládal z přílohy .xls za běhu). */
?>
<div class="obsah"><?= $uzel['telo'] ?></div>
<?php if ($uzel['file'] !== ''): ?>
<p class="ke-stazeni">Ke stažení:
    <a href="<?= htmlspecialchars($uzel['file'], ENT_QUOTES) ?>"><?=
        htmlspecialchars($uzel['file_nazev'] ?: basename($uzel['file'])) ?></a></p>
<?php endif; ?>
