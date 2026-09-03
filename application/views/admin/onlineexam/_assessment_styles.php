<style>
/* Scoped Online Examination layout rules. Keep these selectors below
 * .onlineexam-ui so other administration modules retain their current layout. */
.onlineexam-ui .onlineexam-scroll {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}
.onlineexam-ui .onlineexam-scroll > table {
    margin-bottom: 0;
}
.onlineexam-ui .onlineexam-nowrap,
.onlineexam-ui .onlineexam-action-cell,
.onlineexam-ui .onlineexam-action-cell form {
    white-space: nowrap;
}
.onlineexam-ui .onlineexam-action-cell form {
    display: inline-block !important;
    margin: 0 1px;
    vertical-align: middle;
}
.onlineexam-ui .onlineexam-action-cell .btn {
    float: none;
    margin: 0 1px;
}
.onlineexam-ui .onlineexam-list-table {
    min-width: 1080px;
}
.onlineexam-ui .onlineexam-list-table th:last-child,
.onlineexam-ui .onlineexam-list-table td:last-child {
    min-width: 150px;
    white-space: nowrap;
}
.onlineexam-ui .onlineexam-list-table td:last-child form {
    display: inline-block !important;
    margin: 0 1px;
    vertical-align: middle;
}
.onlineexam-ui .assessment-box-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.onlineexam-ui .assessment-box-header .box-title {
    min-width: 0;
}
.onlineexam-ui .assessment-box-header .box-tools {
    position: static;
    flex: 0 0 auto;
    margin: 0;
    white-space: nowrap;
}
.onlineexam-ui .assessment-toolbar {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    width: 100%;
    overflow-x: auto;
    padding-bottom: 2px;
    -webkit-overflow-scrolling: touch;
}
.onlineexam-ui .assessment-toolbar .btn,
.onlineexam-ui .assessment-toolbar form {
    flex: 0 0 auto;
    margin: 0;
    white-space: nowrap;
}
.onlineexam-ui .assessment-meta-table {
    min-width: 720px;
    table-layout: fixed;
}
.onlineexam-ui .assessment-meta-table td {
    width: 25%;
    padding: 8px 12px;
    vertical-align: top;
}
.onlineexam-ui .assessment-rule-options {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 8px 20px;
}
.onlineexam-ui .assessment-rule-options .checkbox-inline {
    display: inline-flex;
    align-items: flex-start;
    gap: 6px;
    margin: 0;
    padding-left: 0;
}
.onlineexam-ui .assessment-rule-options .checkbox-inline input {
    position: static;
    margin: 3px 0 0;
}
.onlineexam-ui .paper-field-guide {
    margin-bottom: 12px;
}
.onlineexam-ui .paper-field-guide dl {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px 18px;
    margin: 0;
}
.onlineexam-ui .paper-field-guide dt,
.onlineexam-ui .paper-field-guide dd {
    display: inline;
    margin: 0;
}
.onlineexam-ui .paper-field-guide dd:after {
    content: '';
    display: block;
}
.onlineexam-ui .paper-form-scroll,
.onlineexam-ui .paper-section-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.onlineexam-ui .paper-form-grid {
    display: grid;
    grid-template-columns: minmax(220px, 2.3fr) minmax(90px, .8fr) minmax(145px, 1.2fr) minmax(92px, .8fr) minmax(105px, .9fr) minmax(120px, 1fr) minmax(82px, .7fr);
    gap: 10px;
    min-width: 990px;
    align-items: end;
}
.onlineexam-ui .paper-form-grid.paper-form-grid-secondary {
    grid-template-columns: minmax(190px, 1.2fr) minmax(190px, 1.2fr) minmax(260px, 2fr) minmax(92px, .7fr) minmax(90px, .7fr);
    min-width: 900px;
}
.onlineexam-ui .paper-form-grid .form-group {
    margin-bottom: 10px;
}
.onlineexam-ui .paper-section-grid {
    display: grid;
    grid-template-columns: minmax(170px, 1.2fr) minmax(170px, 1.2fr) minmax(110px, .7fr) minmax(250px, 2fr) minmax(44px, auto);
    gap: 8px;
    min-width: 820px;
    align-items: center;
}
.onlineexam-ui .paper-summary-table {
    min-width: 660px;
    table-layout: fixed;
}
.onlineexam-ui .paper-summary-table td {
    width: 25%;
    padding: 7px 10px;
    vertical-align: top;
}
.onlineexam-ui .paper-header-labels {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.onlineexam-ui .question-assignment-table {
    min-width: 1120px;
    table-layout: fixed;
}
.onlineexam-ui .question-assignment-table th,
.onlineexam-ui .question-assignment-table td {
    vertical-align: middle;
}
.onlineexam-ui .question-assignment-table .question-text-cell {
    width: 300px;
    white-space: normal;
    overflow-wrap: anywhere;
}
.onlineexam-ui .question-assignment-table .question-assigned-cell {
    width: 170px;
    white-space: normal;
}
.onlineexam-ui .question-assignment-table .question-assigned-cell .label {
    display: inline-block;
    max-width: 100%;
    white-space: normal;
    line-height: 1.35;
}
.onlineexam-ui .question-assignment-table .question-number-cell {
    width: 90px;
}
.onlineexam-ui .question-assignment-table .question-scheme-cell {
    width: 210px;
}
.onlineexam-ui .question-assignment-table .question-actions-cell {
    width: 155px;
    white-space: nowrap;
}
.onlineexam-ui .question-assignment-table .form-control {
    min-width: 78px;
}
.onlineexam-ui .structured-question-table {
    min-width: 760px;
}
.onlineexam-ui .candidate-roster-table {
    min-width: 820px;
}
.onlineexam-ui .candidate-roster-table th,
.onlineexam-ui .candidate-roster-table td {
    vertical-align: middle;
}
.onlineexam-ui .operations-table {
    min-width: 900px;
}
.onlineexam-ui .operations-wide-table {
    min-width: 1080px;
}
.onlineexam-ui .compact-form-row .form-group {
    margin-bottom: 10px;
}

@media (max-width: 991px) {
    .onlineexam-ui.content-wrapper .content,
    .onlineexam-ui .content {
        padding-left: 10px;
        padding-right: 10px;
    }
    .onlineexam-ui .box-body,
    .onlineexam-ui .box-footer {
        padding-left: 12px;
        padding-right: 12px;
    }
    .onlineexam-ui .assessment-box-header {
        flex-wrap: wrap;
    }
    .onlineexam-ui .assessment-box-header .box-tools {
        width: 100%;
        overflow-x: auto;
        padding-top: 4px;
    }
    .onlineexam-ui .assessment-rule-options {
        display: block;
    }
    .onlineexam-ui .assessment-rule-options .checkbox-inline {
        display: flex;
        width: 100%;
        margin: 0 0 9px !important;
    }
    .onlineexam-ui .paper-field-guide dl {
        grid-template-columns: 1fr;
    }
    .onlineexam-ui .box-footer .btn {
        max-width: 100%;
        white-space: normal;
    }
    .onlineexam-ui .assessment-toolbar .btn {
        white-space: nowrap;
    }
    .onlineexam-ui .btn {
        min-height: 34px;
    }
}
</style>
