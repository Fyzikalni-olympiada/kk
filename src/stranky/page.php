<?php
/*
 * Obyčejná textová stránka (dřív presenter TextPage).
 *
 * Datum a autora nese jen novinka; na její vlastní stránce se vypisují
 * bez odkazu — trvalý odkaz vede právě sem, odkazovat odsud na sebe
 * nedává smysl (stejně to dělá fyzikalniolympiada.cz).
 */
?>
<div class="obsah"><?= $uzel['telo'] ?></div>
<?php if ($uzel['datum'] !== null): ?>
<p class="createdate"><?= datum_cesky($uzel['datum']) ?><?php
    if ($uzel['autor'] !== ''): ?> &middot; <span class="small"><?=
    htmlspecialchars($uzel['autor']) ?></span><?php endif; ?></p>
<?php endif; ?>
