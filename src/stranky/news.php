<?php
/*
 * Rozcestník novinek: tělo a pod ním novinky vypsané celé.
 * Datum je zmrazené při migraci — stará šablona ho počítala z date('Y')
 * za běhu, což by se ve statickém webu rozešlo s realitou.
 */
?>
<div class="obsah"><?= $uzel['telo'] ?></div>
<div class="news">
<?php foreach (obsah_deti($uzel['novinky'] ?: $uzel['pathname'], false) as $item): ?>
    <?php /* odkaz_uzlu respektuje typ: novinka typu link bez cíle se vypíše
             textem, místo odkazu na stránku, která se nevykresluje */ ?>
    <h2><?= odkaz_uzlu($item) ?></h2>
    <div class="obsah"><?= $item['telo'] ?></div>
    <p class="createdate"><?= datum_cesky($item['datum']) ?><?php
        if ($item['autor'] !== ''): ?> &middot; <span class="small"><?=
        htmlspecialchars($item['autor']) ?></span><?php endif; ?></p>
    <span class="article_separator">&nbsp;</span>
<?php endforeach; ?>
</div>
