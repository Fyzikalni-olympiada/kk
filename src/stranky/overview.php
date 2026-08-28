<?php /* Rozcestník: tělo + seznam potomků (dřív presenter Overview). */ ?>
<div class="obsah"><?= $uzel['telo'] ?></div>
<?= seznam_deti($uzel['pathname']) ?>
