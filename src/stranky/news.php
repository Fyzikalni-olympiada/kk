<?php
/*
 * Rozcestník novinek: tělo a pod ním novinky nejnovějšího ročníku.
 * Trvalý odkaz visí na datu (stejně jako na fyzikalniolympiada.cz);
 * nadpis zůstává textem, aby na řádku nebyly dva odkazy na totéž.
 *
 * Datum je zmrazené při migraci — stará šablona ho počítala z date('Y')
 * za běhu, což by se ve statickém webu rozešlo s realitou.
 */
?>
<div class="obsah"><?= $uzel['telo'] ?></div>
<div class="news">
<?php foreach (novinky_aktualniho_rocniku(
        obsah_deti($uzel['novinky'] ?: $uzel['pathname'], false)) as $item): ?>
    <h2><?= htmlspecialchars($item['nadpis']) ?></h2>
    <div class="obsah"><?= $item['telo'] ?></div>
    <p class="createdate">
        <?php if (typ_ma($item, 'stranka')): ?>
        <a href="<?= htmlspecialchars($item['pathname'], ENT_QUOTES) ?>"
           title="Trvalý odkaz na novinku"><?= datum_cesky($item['datum']) ?></a>
        <?php else: ?>
        <?= datum_cesky($item['datum']) ?>
        <?php endif; ?>
        <?php if ($item['autor'] !== ''): ?>&middot; <span class="small"><?=
            htmlspecialchars($item['autor']) ?></span><?php endif; ?>
    </p>
    <span class="article_separator">&nbsp;</span>
<?php endforeach; ?>
</div>
