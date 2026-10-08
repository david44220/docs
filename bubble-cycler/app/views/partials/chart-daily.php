<?php
/**
 * Grouped column chart: bubbles bought vs expired per day.
 * Colors come from the validated chart tokens in app.css (--series-1 / --series-2).
 *
 * @var array $series  [['day' => 'Y-m-d', 'bought' => int, 'expired' => int], …]
 */
$values = array_merge(array_column($series, 'bought'), array_column($series, 'expired'));
$max = max(1, ...$values);
$raw = $max / 4;
$magnitude = 10 ** floor(log10($raw));
$step = 1;
foreach ([1, 2, 5, 10] as $m) {
    if ($m * $magnitude >= $raw) {
        $step = max(1, (int) ($m * $magnitude));
        break;
    }
}
$top = (int) (ceil($max / $step) * $step);
$ticks = range(0, $top, $step);
$totalBought = array_sum(array_column($series, 'bought'));
$totalExpired = array_sum(array_column($series, 'expired'));
?>
<figure class="chart" data-chart>
    <figcaption class="chart__head">
        <div>
            <h2 class="card__title">Bubbles per day</h2>
            <p class="card__sub">Last <?= count($series) ?> days (UTC) · <?= number_format($totalBought) ?> bought · <?= number_format($totalExpired) ?> expired</p>
        </div>
        <ul class="chart__legend">
            <li><i class="chart__key chart__key--1"></i>Bought</li>
            <li><i class="chart__key chart__key--2"></i>Expired</li>
        </ul>
    </figcaption>

    <div class="chart__body">
        <div class="chart__plot">
            <?php foreach ($ticks as $tick): ?>
                <span class="chart__grid" style="bottom: <?= round($tick / $top * 100, 3) ?>%"><span class="chart__tick"><?= number_format($tick) ?></span></span>
            <?php endforeach; ?>
            <div class="chart__cols">
                <?php foreach ($series as $i => $point):
                    $label = fmt_date($point['day'] . ' 12:00:00', 'D, M j');
                ?>
                    <div class="chart__col" tabindex="0"
                         data-tip-title="<?= e($label) ?>"
                         data-tip-bought="<?= (int) $point['bought'] ?>"
                         data-tip-expired="<?= (int) $point['expired'] ?>"
                         aria-label="<?= e($label . ': ' . $point['bought'] . ' bought, ' . $point['expired'] . ' expired') ?>">
                        <span class="chart__bar chart__bar--1" style="height: <?= round($point['bought'] / $top * 100, 3) ?>%"></span>
                        <span class="chart__bar chart__bar--2" style="height: <?= round($point['expired'] / $top * 100, 3) ?>%"></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="chart__x" aria-hidden="true">
            <?php foreach ($series as $i => $point): $noon = $point['day'] . ' 12:00:00'; ?>
                <?php /* The month is named on the first day and when it changes: "Sep 28 · 29 · 30 · Oct 1 · 2". */ ?>
                <span class="<?= $i % 2 === 1 ? 'is-odd' : '' ?>"><?php if ($i === 0 || fmt_date($noon, 'j') === '1'): ?><span class="chart__month"><?= e(fmt_date($noon, 'M')) ?> </span><?php endif; ?><?= e(fmt_date($noon, 'j')) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="chart__tooltip" data-chart-tooltip hidden></div>

    <details class="chart__table">
        <summary>Show as table</summary>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead><tr><th>Day</th><th class="num">Bought</th><th class="num">Expired</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($series) as $point): ?>
                    <tr><td><?= e(fmt_date($point['day'] . ' 12:00:00', 'D, M j')) ?></td><td class="num"><?= number_format($point['bought']) ?></td><td class="num"><?= number_format($point['expired']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
</figure>
