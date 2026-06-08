<?php
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;

$this->title = $model->isNewRecord ? 'Create Sync Pair' : 'Edit Sync Pair';

$localOptions = ArrayHelper::map($localConns, 'id', function($c) {
    return "{$c->label}  ({$c->host}:{$c->port}/{$c->dbname})";
});
$cloudOptions = ArrayHelper::map($cloudConns, 'id', function($c) {
    return "{$c->label}  ({$c->host}:{$c->port}/{$c->dbname})";
});
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><?= $model->isNewRecord ? 'Create a new sync pair' : 'Edit sync pair' ?></span>
            </div>
            <div class="p-4">

                <?php $form = ActiveForm::begin(); ?>

                    <!-- Info note -->
                    <div class="alert alert-info" style="font-size:12.5px;">
                        <i class="bi bi-info-circle me-1"></i>
                        A sync pair links a <strong>local</strong> database to a <strong>cloud</strong> database.
                        Records are pushed from local → cloud on each scheduled run.
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'label')->textInput(['placeholder' => 'Optional friendly name, e.g. MFG Sync'])->label('Pair label (optional)') ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <?= $form->field($model, 'local_conn_id')->dropDownList($localOptions, [
                                'prompt' => '— Select local DB —'
                            ])->label('Local database') ?>
                        </div>
                        <div class="col-6">
                            <?= $form->field($model, 'cloud_conn_id')->dropDownList($cloudOptions, [
                                'prompt' => '— Select cloud DB —'
                            ])->label('Cloud database') ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <?= $form->field($model, 'interval_minutes')->dropDownList($model->getIntervalOptions())->label('Sync interval') ?>
                        </div>
                        <div class="col-6">
                            <?= $form->field($model, 'conflict_strategy')->dropDownList($model->getConflictOptions())->label('Conflict strategy') ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <?= $form->field($model, 'tables_json')->textInput([
                            'placeholder' => '["*"] for all, or ["table1","table2"]'
                        ])->label('Tables to sync (JSON array)') ?>
                        <div class="form-text">Use <code>["*"]</code> to sync all tables, or specify: <code>["orders","customers"]</code></div>
                    </div>

                    <div class="d-flex gap-2">
                        <?= Html::submitButton(
                            '<i class="bi bi-floppy me-1"></i>' . ($model->isNewRecord ? 'Create pair' : 'Save changes'),
                            ['class' => 'btn btn-success']
                        ) ?>
                        <a href="<?= \yii\helpers\Url::to(['/dashboard/index']) ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>