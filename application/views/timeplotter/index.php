<script>
    var lang_statistics_timeplotter_contacts_plotted = '<?= __("contacts were plotted"); ?>';
    var lang_statistics_timeplotter_chart_header = '<?= __("Time Distribution"); ?>';
    var lang_statistics_timeplotter_number_of_qsos = '<?= __("Number of QSOs"); ?>';
    var lang_general_word_time = '<?= __("Time"); ?>';
    var lang_statistics_timeplotter_callsigns_worked = '<?= __("Callsign(s) worked (max 5)"); ?>';
</script>

<div class="container timeplotter mt-4 mb-4">
    <h2 class="mb-2"><?= __("Timeplotter"); ?></h2>


    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="timeplotter-filters" class="row gy-3 gx-3 align-items-end" novalidate>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="dxcc"><?= __("DXCC"); ?></label>
                    <select id="dxcc" name="dxcc" class="form-select">
                        <option value="All"><?= __("All"); ?></option>
                        <?php
                        if ($dxcc_list->num_rows() > 0) {
                                foreach ($dxcc_list->result() as $dxcc) {
                                    echo '<option value=' . $dxcc->adif . '>' . $dxcc->prefix . ' - ' . ucwords(strtolower($dxcc->name));
                                    if ($dxcc->end != null) {
                                        echo ' ('.__("Deleted DXCC").')';
                                    }
                                    echo '</option>';
                                }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="cqzone"><?= __("CQ Zone"); ?></label>
                    <select id="cqzone" name="cqzone" class="form-select">
                        <option value="All"><?= __("All"); ?></option>
                        <?php
                        for ($i = 1; $i<=40; $i++) {
                            echo '<option value='. $i . '>'. $i .'</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="band"><?= __("Band"); ?></label>
                    <select id="band" name="band" class="form-select">
                        <option value="All"><?= __("All"); ?></option>
                        <?php foreach($worked_bands as $band) {
                            echo '<option value="' . $band . '">' . $band . '</option>'."\n";
                        } ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="mode"><?= __("Mode"); ?></label>
                    <select id="mode" name="mode" class="form-select">
                        <option value="All"><?= __("All"); ?></option>
                        <?php
                        foreach ($modes as $mode) {
							if ($mode->submode ?? '' == '') {
								echo '<option value="' . $mode . '">' . strtoupper($mode) . '</option>' . "\n";
							}
						}
                        ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <button id="button1id" type="button" name="button1id" class="btn btn-primary w-100 ld-ext-right" onclick="timeplot(this.form);"><?= __("Show"); ?><div class="ld ld-ring ld-spin"></div></button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3" id="timeplotter-summary" style="display:none;">
        <div class="col-6 col-md-3">
            <div class="card summary-card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Best Time Window</p>
                    <h5 class="mb-0" id="summary-best-window">-</h5>
                    <span class="text-muted small" id="summary-best-window-count"></span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card summary-card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Best Band</p>
                    <h5 class="mb-0" id="summary-best-band">-</h5>
                    <span class="text-muted small" id="summary-best-band-count"></span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card summary-card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Best Mode</p>
                    <h5 class="mb-0" id="summary-best-mode">-</h5>
                    <span class="text-muted small" id="summary-best-mode-count"></span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card summary-card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Total QSOs plotted</p>
                    <h5 class="mb-0" id="summary-total-qsos">-</h5>
                    <span class="text-muted small" id="summary-date-range"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3" id="timeplotter-heatmap-card" style="display:none;">
        <div class="card-header">
            <span>Activity by Time of Day (UTC)</span>
        </div>
        <div class="card-body" style="min-height: 200px;">
            <div class="heatmap-legend mb-3">
                <span class="text-muted small me-2">Relative Intensity</span>
                <span class="legend-item"><span class="legend-swatch none"></span>None</span>
                <span class="legend-item"><span class="legend-swatch glanceyear-legend-1"></span>Low</span>
                <span class="legend-item"><span class="legend-swatch glanceyear-legend-2"></span>Medium</span>
                <span class="legend-item"><span class="legend-swatch glanceyear-legend-3"></span>High</span>
                <span class="legend-item"><span class="legend-swatch glanceyear-legend-4"></span>Very High</span>
            </div>
            <div class="heatmap-grid-wrapper">
                <div id="timeplotterHeatmap" class="heatmap-grid" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div id="timeplotter_div"></div>
</div>

<style>
    .summary-card { border: 1px solid #eef0f2; }
    .summary-card h5 { font-weight: 600; }
    .heatmap-grid-wrapper { overflow-x: auto; padding-top: 4px; }
    .heatmap-grid { display: grid; grid-template-columns: repeat(24, minmax(0, 1fr)); gap: 2px; max-width: 100%; }
    .heatmap-header {
        text-align: center;
        padding: 6px 4px;
        /* font-size: 0.75rem; */
        /* font-weight: 700; */
        /* color: #586069; */
        /* background: #f8f9fa; */
        /* border: 1px solid #e1e4e8; */
    }
    .heatmap-cell {
        text-align: center;
        padding: 8px 4px;
        /* font-size: 0.75rem; */
        /* font-weight: 600; */
        border-radius: 3px;
        border: 1px solid #d1d5da;
        cursor: default;
    }
    .heatmap-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .legend-item { display: inline-flex; align-items: center; gap: 4px; font-size: 0.8rem;}
    .legend-swatch { width: 16px; height: 16px; border: 1px solid #d1d5da; border-radius: 3px; display: inline-block; }
    .legend-swatch.none { background-color: #ebedf0; }

    @media (max-width: 992px) {
        .heatmap-grid { grid-template-columns: repeat(12, minmax(0, 1fr)); }
    }
</style>
