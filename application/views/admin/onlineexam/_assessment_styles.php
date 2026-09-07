<style>
/* Scoped Online Examination layout rules. Keep these selectors below
 * .onlineexam-ui so other administration modules retain their current layout. */
.onlineexam-ui .content-header h1,
.onlineexam-ui .box-title,
.onlineexam-ui .alert,
.onlineexam-ui .help-block {
    max-width: 100%;
    overflow-wrap: anywhere;
}
.onlineexam-ui .onlineexam-scroll {
    display: block;
    position: relative;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-inline: contain;
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
    overflow-wrap: anywhere;
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
.onlineexam-ui .paper-form-scroll,
.onlineexam-ui .paper-section-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.onlineexam-ui .paper-form-grid {
    display: grid;
    grid-template-columns: minmax(220px, 2.3fr) minmax(90px, .8fr) minmax(145px, 1.2fr) minmax(92px, .8fr) minmax(120px, 1fr) minmax(82px, .7fr);
    gap: 10px;
    min-width: 850px;
    align-items: end;
}
.onlineexam-ui .paper-form-grid.paper-form-grid-secondary {
    grid-template-columns: minmax(190px, 1.2fr) minmax(190px, 1.2fr) minmax(260px, 2fr) minmax(92px, .7fr) minmax(90px, .7fr);
    min-width: 900px;
}
.onlineexam-ui .paper-form-grid .form-group {
    margin-bottom: 10px;
}
.onlineexam-ui .paper-form-grid > *,
.onlineexam-ui .paper-section-grid > *,
.onlineexam-ui .question-bank-filter-grid > * {
    min-width: 0;
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
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 5px;
}
.onlineexam-ui .paper-header-labels .label,
.onlineexam-ui .paper-header-labels .badge {
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
}
.onlineexam-ui .paper-section-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.onlineexam-ui .paper-section-main {
    min-width: 0;
    overflow-wrap: anywhere;
}
.onlineexam-ui .paper-section-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
}
.onlineexam-ui .paper-section-actions form {
    margin: 0;
}
.onlineexam-ui .question-bank-filter-grid {
    display: grid;
    grid-template-columns: minmax(180px, 1.2fr) minmax(180px, 1.2fr) minmax(180px, 1fr) minmax(160px, .9fr) minmax(105px, auto);
    gap: 10px 14px;
    align-items: end;
}
.onlineexam-ui .question-bank-filter-grid .form-group {
    min-width: 0;
    margin-bottom: 15px;
}
.onlineexam-ui .question-bank-filter-grid .btn {
    min-width: 105px;
}
.onlineexam-ui .question-bank-filter-grid .form-control,
.onlineexam-ui .paper-form-grid .form-control,
.onlineexam-ui .paper-section-grid .form-control {
    max-width: 100%;
}
.onlineexam-ui .question-bank-status {
    min-height: 20px;
}
.onlineexam-ui #builder_question_pagination {
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.onlineexam-ui #builder_question_pagination .pagination {
    display: flex;
    float: none !important;
    width: max-content;
    min-width: min-content;
    margin-left: auto;
}
.onlineexam-ui #builder_question_pagination .pagination > li {
    flex: 0 0 auto;
}
.onlineexam-ui .question-assignment-table {
    width: 100%;
    min-width: 1510px;
    table-layout: auto;
}
.onlineexam-ui .question-assignment-table th,
.onlineexam-ui .question-assignment-table td {
    vertical-align: middle;
    overflow-wrap: normal;
    word-break: normal;
}
.onlineexam-ui .question-assignment-table .question-text-cell {
    width: 300px;
    min-width: 300px;
    max-width: 360px;
    white-space: normal;
    overflow-wrap: anywhere;
}
.onlineexam-ui .question-assignment-table .question-type-cell {
    width: 130px;
    min-width: 130px;
    white-space: normal;
    overflow-wrap: anywhere;
}
.onlineexam-ui .question-assignment-table .question-assigned-cell {
    width: 190px;
    min-width: 190px;
    white-space: normal;
    overflow-wrap: anywhere;
}
.onlineexam-ui .question-assignment-table .question-assigned-cell .label {
    display: inline-block;
    max-width: 100%;
    white-space: normal;
    line-height: 1.35;
}
.onlineexam-ui .question-assignment-table .question-number-cell {
    width: 105px;
    min-width: 105px;
}
.onlineexam-ui .question-assignment-table .question-scheme-cell {
    width: 230px;
    min-width: 230px;
}
.onlineexam-ui .question-assignment-table .question-required-cell {
    width: 135px;
    min-width: 135px;
    white-space: nowrap;
}
.onlineexam-ui .question-assignment-table .question-actions-cell {
    width: 180px;
    min-width: 180px;
    white-space: nowrap;
}
.onlineexam-ui .question-assignment-table .question-required-cell .checkbox-inline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding-left: 0;
}
.onlineexam-ui .question-assignment-table .question-required-cell input {
    position: static;
    margin: 0;
}
.onlineexam-ui .question-assignment-table .question-actions-cell .btn {
    float: none;
    margin: 2px 1px;
}
.onlineexam-ui .question-assignment-table .form-control {
    width: 100%;
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
.onlineexam-ui .assessment-form-actions,
.onlineexam-ui .workflow-author-actions,
.onlineexam-ui .single-action-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.onlineexam-ui .single-action-footer {
    justify-content: flex-end;
}
.onlineexam-ui .assessment-form-actions .btn,
.onlineexam-ui .workflow-author-actions .btn,
.onlineexam-ui .single-action-footer .btn {
    float: none !important;
    margin: 0;
}
.onlineexam-ui .operations-meta {
    overflow-wrap: anywhere;
}
.onlineexam-ui .operations-inline-form,
.onlineexam-ui .attempt-void-form,
.onlineexam-ui .incident-resolution-form,
.onlineexam-ui .sync-action-form,
.onlineexam-ui .marking-inline-form {
    display: flex !important;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 6px;
    white-space: normal;
}
.onlineexam-ui .operations-inline-form .form-group {
    margin: 0;
}
.onlineexam-ui .operations-inline-form .operations-extra-time {
    width: 90px;
}
.onlineexam-ui .operations-inline-form .operations-note-field {
    flex: 1 1 180px;
    min-width: 160px;
}
.onlineexam-ui .attempt-actions-cell {
    min-width: 270px;
}
.onlineexam-ui .attempt-actions-cell > .btn {
    margin-bottom: 6px;
}
.onlineexam-ui .attempt-void-form .form-control {
    flex: 1 1 130px;
    width: auto;
    min-width: 120px;
}
.onlineexam-ui .incident-resolution-form .form-control,
.onlineexam-ui .sync-action-form .form-control {
    flex: 1 1 240px;
    width: auto;
    min-width: 180px;
}
.onlineexam-ui .sync-action-form {
    align-items: stretch;
    margin-top: 5px;
}
.onlineexam-ui .sync-action-form .btn {
    white-space: normal;
}
.onlineexam-ui .theory-response {
    max-width: 100%;
    white-space: pre-wrap;
    overflow-x: auto;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.onlineexam-ui .theory-attachment,
.onlineexam-ui .theory-attachment .btn {
    display: inline-flex;
    max-width: 100%;
    white-space: normal;
    text-align: left;
    overflow-wrap: anywhere;
}
.onlineexam-ui .theory-attachment .btn {
    align-items: flex-start;
}
.onlineexam-ui.onlineexam-theory-review-page .dl-horizontal dd,
.onlineexam-ui.onlineexam-theory-review-page .dl-horizontal code {
    overflow-wrap: anywhere;
    word-break: break-word;
}
.onlineexam-ui .marking-history-note {
    display: inline-block;
    margin: 6px 0;
}
.onlineexam-ui .marking-finalize .btn {
    max-width: 100%;
    white-space: normal;
}

@media (max-width: 1199px) {
    .onlineexam-ui .paper-form-scroll,
    .onlineexam-ui .paper-section-scroll {
        overflow: visible;
    }
    .onlineexam-ui .paper-form-grid,
    .onlineexam-ui .paper-form-grid.paper-form-grid-secondary {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        min-width: 0;
    }
    .onlineexam-ui .paper-section-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        min-width: 0;
    }
    .onlineexam-ui .question-bank-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .onlineexam-ui .question-bank-filter-grid .question-bank-search-group {
        grid-column: 1 / -1;
    }
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
    .onlineexam-ui .paper-form-grid,
    .onlineexam-ui .paper-form-grid.paper-form-grid-secondary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .onlineexam-ui .box-footer .btn,
    .onlineexam-ui .marking-finalize .btn {
        max-width: 100%;
        white-space: normal;
    }
    .onlineexam-ui .assessment-toolbar .btn {
        white-space: nowrap;
    }
    .onlineexam-ui .assessment-toolbar .btn,
    .onlineexam-ui .assessment-form-actions .btn,
    .onlineexam-ui .workflow-author-actions .btn,
    .onlineexam-ui .single-action-footer .btn,
    .onlineexam-ui .question-bank-filter-grid .btn {
        min-height: 34px;
    }
}

@media (max-width: 767px) {
    .onlineexam-ui .paper-form-grid,
    .onlineexam-ui .paper-form-grid.paper-form-grid-secondary,
    .onlineexam-ui .paper-section-grid,
    .onlineexam-ui .question-bank-filter-grid {
        grid-template-columns: 1fr;
    }
    .onlineexam-ui .question-bank-filter-grid .question-bank-search-group {
        grid-column: auto;
    }
    .onlineexam-ui .question-bank-filter-grid .btn,
    .onlineexam-ui .assessment-form-actions .btn,
    .onlineexam-ui .workflow-author-actions .btn,
    .onlineexam-ui .single-action-footer .btn,
    .onlineexam-ui .marking-finalize .btn {
        width: 100%;
    }
    .onlineexam-ui .assessment-form-actions,
    .onlineexam-ui .workflow-author-actions,
    .onlineexam-ui .single-action-footer {
        align-items: stretch;
        flex-direction: column;
    }
    .onlineexam-ui .paper-section-item {
        flex-direction: column;
    }
    .onlineexam-ui .paper-section-actions {
        width: 100%;
        justify-content: flex-end;
    }
    .onlineexam-ui .operations-inline-form,
    .onlineexam-ui .attempt-void-form,
    .onlineexam-ui .incident-resolution-form,
    .onlineexam-ui .sync-action-form,
    .onlineexam-ui .marking-inline-form {
        align-items: stretch;
        flex-direction: column;
    }
    .onlineexam-ui .operations-inline-form .operations-extra-time,
    .onlineexam-ui .operations-inline-form .operations-note-field,
    .onlineexam-ui .attempt-void-form .form-control,
    .onlineexam-ui .incident-resolution-form .form-control,
    .onlineexam-ui .sync-action-form .form-control {
        width: 100%;
        min-width: 0;
    }
}
</style>
