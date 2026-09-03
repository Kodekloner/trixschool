<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $this->load->view('admin/onlineexam/_assessment_styles'); ?>
<div class="content-wrapper onlineexam-ui onlineexam-analysis-page">
    <section class="content-header">
        <h1><i class="fa fa-bar-chart"></i> <?php echo html_escape($exam->exam); ?> <small>frozen paper and question analysis</small></h1>
        <a href="<?php echo base_url('admin/onlineexam/operations/' . (int) $exam->id); ?>">&larr; Back to operations</a>
    </section>
    <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Paper performance</h3></div>
            <div class="box-body table-responsive onlineexam-scroll">
                <table class="table table-bordered table-striped operations-table">
                    <thead><tr><th>Revision / paper</th><th>Type</th><th>Candidates</th><th>Submitted</th><th>Average raw</th><th>Average contribution</th></tr></thead>
                    <tbody>
                    <?php foreach ($analysis['papers'] as $paper) { ?>
                        <tr>
                            <td>R<?php echo (int) $paper['revision']; ?> — <?php echo html_escape($paper['paper_title']); ?></td>
                            <td><?php echo html_escape($paper['paper_type']); ?></td>
                            <td><?php echo (int) $paper['candidates']; ?></td>
                            <td><?php echo (int) $paper['submitted']; ?></td>
                            <td><?php echo $paper['average_raw_score'] === null ? '—' : number_format((float) $paper['average_raw_score'], 2) . ' / ' . number_format((float) $paper['raw_max_score'], 2); ?></td>
                            <td><?php echo $paper['average_contribution'] === null ? '—' : number_format((float) $paper['average_contribution'], 2) . ' / ' . number_format((float) $paper['contribution_max'], 2); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($analysis['papers'])) { ?><tr><td colspan="6" class="text-center text-muted">No attempt data is available yet.</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">Question analysis</h3></div>
            <div class="box-body table-responsive onlineexam-scroll">
                <table class="table table-bordered table-condensed operations-wide-table">
                    <thead><tr><th>Revision / paper / question</th><th>Type</th><th>Candidates</th><th>Answered</th><th>Correct / incorrect</th><th>Average mark</th><th>Facility</th></tr></thead>
                    <tbody>
                    <?php foreach ($analysis['questions'] as $question) { ?>
                        <tr>
                            <td><small>R<?php echo (int) $question['revision']; ?> — <?php echo html_escape($question['paper_title']); ?></small><br><?php echo html_escape(mb_substr(trim(strip_tags($question['question_text'])), 0, 220)); ?></td>
                            <td><?php echo html_escape($question['question_type']); ?></td>
                            <td><?php echo (int) $question['candidates']; ?></td>
                            <td><?php echo (int) $question['answered']; ?></td>
                            <td><?php echo (int) $question['correct']; ?> / <?php echo (int) $question['incorrect']; ?></td>
                            <td><?php echo $question['average_mark'] === null ? '—' : number_format((float) $question['average_mark'], 2) . ' / ' . number_format((float) $question['marks'], 2); ?></td>
                            <td><?php echo number_format((float) $question['facility_percent'], 2); ?>%</td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($analysis['questions'])) { ?><tr><td colspan="7" class="text-center text-muted">No frozen question responses are available yet.</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
