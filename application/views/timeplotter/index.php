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

    <div id="timeplotter_div"></div>
</div>
