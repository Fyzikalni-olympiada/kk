<?php
/*
 * Rozcestník výsledkových listin na jedné stránce.
 *
 * Nahrazuje trojí proklik ročník → kolo → kategorie: každý ročník je jeden
 * řádek, v něm kola a v nich kategorie jako odkazy. Bez tabulky, aby se to
 * na úzkém displeji prostě zalomilo.
 *
 * Kolo je uzel, který sám má potomky. Kde úroveň kol chybí (67. ročník Prahy
 * vede rovnou na Osmo), berou se listiny jako krajské kolo — o jiné se
 * v takovém ročníku nejedná.
 */
?>
<div class="obsah"><?= $uzel['telo'] ?></div>

<ul class="rozcestnik-vysledku">
<?php foreach (obsah_deti($uzel['pathname']) as $rok): ?>
    <?php
    $kola = [];
    $bez_kola = [];
    foreach (obsah_deti($rok['pathname']) as $polozka) {
        $deti = obsah_deti($polozka['pathname']);
        if ($deti) {
            $kola[] = ['nazev' => $polozka['title'], 'uzel' => $polozka, 'kat' => $deti];
        } else {
            $bez_kola[] = $polozka;
        }
    }
    /* Listiny bez úrovně kola jsou krajské; když ročník krajské kolo má,
       přilepí se k němu, jinak se pro ně skupina založí. */
    if ($bez_kola) {
        $index = null;
        foreach ($kola as $i => $k) {
            if (str_starts_with($k['nazev'], 'Krajské')) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            $kola[] = ['nazev' => 'Krajské kolo', 'uzel' => null, 'kat' => $bez_kola];
        } else {
            $kola[$index]['kat'] = array_merge($kola[$index]['kat'], $bez_kola);
        }
    }
    if (!$kola) {
        continue;
    }
    ?>
    <li>
        <span class="rok"><?= odkaz_uzlu($rok) ?></span>
        <span class="kola">
        <?php foreach ($kola as $kolo): ?>
            <span class="kolo">
                <span class="kolo-nazev"><?= htmlspecialchars(
                    preg_replace('~\s*kolo$~u', '', $kolo['nazev'])) ?></span>
                <?php foreach ($kolo['kat'] as $kat): ?>
                    <?php
                    [$klic, $okres] = vysledky_kategorie($kat['title']);
                    $cil = odkaz_cil($kat);
                    ?>
                    <a class="listina<?= vede_do_osma($cil) ? ' osmo' : '' ?>"
                       href="<?= htmlspecialchars($cil, ENT_QUOTES) ?>"
                       title="<?= htmlspecialchars($kat['title'], ENT_QUOTES) ?>"><?=
                        htmlspecialchars($klic)
                        ?><?php if ($okres): ?><span class="okres"><?=
                        htmlspecialchars($okres) ?></span><?php endif; ?></a>
                <?php endforeach; ?>
            </span>
        <?php endforeach; ?>
        </span>
    </li>
<?php endforeach; ?>
</ul>
