<?php

$resultSummaryContext = isset($resultSummaryContext) && is_array($resultSummaryContext)
    ? $resultSummaryContext
    : ['number_in_class' => 0, 'grade_key' => [], 'grade_summary' => '', 'cumulative_average' => null];
$showPromotionOutcome = !empty($showPromotionOutcome);
$showCumulativeAverage = !empty($showCumulativeAverage);
$promotionOutcome = isset($promotionOutcome) && is_array($promotionOutcome)
    ? $promotionOutcome
    : build_promotion_outcome('pending', '', 'system', 'unavailable');
$promotionDecision = preg_replace('/[^a-z_]/', '', (string) ($promotionOutcome['decision'] ?? 'pending'));
$promotionStatus = str_replace('_', '-', $promotionDecision);
$resultSummaryPanelSections = $resultSummaryPanelSections ?? array('statistics', 'grade_key', 'promotion');

if (!is_array($resultSummaryPanelSections)) {
    $resultSummaryPanelSections = array($resultSummaryPanelSections);
}

$resultSummaryPanelSections = array_values(array_unique(array_map('strval', $resultSummaryPanelSections)));
$renderResultStatistics = in_array('statistics', $resultSummaryPanelSections, true);
$renderResultGradeKey = in_array('grade_key', $resultSummaryPanelSections, true);
$renderResultPromotion = in_array('promotion', $resultSummaryPanelSections, true);
?>
<?php if ($renderResultStatistics) { ?>
<section class="result-report__summary result-report__summary--statistics" data-result-summary-section="statistics" aria-label="Result statistics">
    <div class="result-report__statistics">
        <div class="result-report__stat">
            <h5 class="result-report__stat-line">
                <span class="result-report__label">NO. IN CLASS:</span>
                <b class="result-report__value"><?php echo (int) ($resultSummaryContext['number_in_class'] ?? 0); ?></b>
            </h5>
        </div>

        <?php if (!empty($resultSummaryContext['grade_summary'])) { ?>
            <div class="result-report__stat">
                <h5 class="result-report__stat-line">
                    <span class="result-report__label">GRADE SUMMARY:</span>
                    <b class="result-report__value"><?php echo htmlspecialchars($resultSummaryContext['grade_summary'], ENT_QUOTES, 'UTF-8'); ?></b>
                </h5>
            </div>
        <?php } ?>

        <?php if ($showCumulativeAverage) { ?>
            <div class="result-report__stat">
                <h5 class="result-report__stat-line">
                    <span class="result-report__label">CUMULATIVE AVERAGE SCORE:</span>
                    <b class="result-report__value">
                        <?php
                        $cumulativeAverage = $resultSummaryContext['cumulative_average'] ?? null;
                        echo $cumulativeAverage === null
                            ? 'N/A'
                            : htmlspecialchars(number_format((float) $cumulativeAverage, 2), ENT_QUOTES, 'UTF-8');
                        ?>
                    </b>
                </h5>
            </div>
        <?php } ?>

    </div>
</section>
<?php } ?>

<?php if ($renderResultGradeKey && !empty($resultSummaryContext['grade_key'])) { ?>
    <section class="result-report__summary result-report__summary--grade-key" data-result-summary-section="grade-key" aria-label="Key to grades">
        <div class="result-report__panel">
            <p class="result-report__panel-title">Key to grades</p>
            <div class="result-report__panel-body">
                <ul class="result-report__grade-key">
                    <?php foreach ($resultSummaryContext['grade_key'] as $gradeKeyItem) { ?>
                        <li class="result-report__grade-item">
                            <b class="result-report__grade-symbol"><?php echo htmlspecialchars($gradeKeyItem['grade'] ?? '', ENT_QUOTES, 'UTF-8'); ?>:</b>
                            <?php echo htmlspecialchars($gradeKeyItem['range'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </section>
<?php } ?>

<?php if ($renderResultPromotion && $showPromotionOutcome) { ?>
    <section class="result-report__summary result-report__summary--promotion" data-result-summary-section="promotion" aria-label="Promotion status">
        <div class="result-report__promotion" data-promotion-status="<?php echo htmlspecialchars($promotionStatus, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="result-report__promotion-label">Promotion status</span>
            <strong class="result-report__promotion-value"><?php echo htmlspecialchars($promotionOutcome['note'] ?? 'PROMOTION PENDING', ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
    </section>
<?php } ?>
