<?php
/*
 * Rozcestník výsledků.  Stará šablona procházela strom do tří úrovní
 * (ResultsOverview.default.phtml:16,25) — kolo => kategorie.
 */
?>
<div class="obsah"><?= $uzel['telo'] ?></div>
<?php foreach (obsah_deti($uzel['pathname']) as $kolo): ?>
    <?php $kategorie = obsah_deti($kolo['pathname']); ?>
    <?php if (!$kategorie): ?>
        <p><?= odkaz_uzlu($kolo) ?></p>
    <?php else: ?>
        <h2><?= odkaz_uzlu($kolo) ?></h2>
        <?= seznam_deti($kolo['pathname']) ?>
    <?php endif; ?>
<?php endforeach; ?>
