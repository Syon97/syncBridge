<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = $isNew ? 'Register Connection' : 'Edit Connection';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><?= $isNew ? 'Register a new database connection' : 'Edit connection' ?></span>
            </div>
            <div class="p-4">

            <?php $form = ActiveForm::begin(['options' => ['class' => '']]); ?>

                <div class="row g-3 mb-3">
                    <div class="col-8">
                        <?= $form->field($model, 'label')->textInput(['placeholder' => 'e.g. manufacturing_local'])->label('Connection label') ?>
                    </div>
                    <div class="col-4">
                        <?= $form->field($model, 'type')->dropDownList(['local' => 'Local', 'cloud' => 'Cloud']) ?>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-8">
                        <?= $form->field($model, 'host')->textInput(['placeholder' => 'localhost or IP / domain']) ?>
                    </div>
                    <div class="col-4">
                        <?= $form->field($model, 'port')->textInput(['placeholder' => '3306']) ?>
                    </div>
                </div>

                <div class="mb-3">
                    <?= $form->field($model, 'dbname')->textInput(['placeholder' => 'my_database'])->label('Database name') ?>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <?= $form->field($model, 'username')->textInput(['placeholder' => 'root']) ?>
                    </div>
                    <div class="col-6">
                        <?= $form->field($model, 'password_plain')->passwordInput([
                            'placeholder' => $isNew ? 'Password' : 'Leave blank to keep existing'
                        ])->label('Password') ?>
                    </div>
                </div>

                <div class="mb-4">
                    <?= $form->field($model, 'notes')->textarea(['rows' => 2, 'placeholder' => 'Optional notes…'])->label('Notes (optional)') ?>
                </div>

                <?php if (!$isNew): ?>
                    <div class="alert alert-secondary" style="font-size:12px;">
                        <i class="bi bi-info-circle me-1"></i>
                        Leave the password blank to keep the existing encrypted password unchanged.
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <?= Html::submitButton(
                        '<i class="bi bi-floppy me-1"></i>' . ($isNew ? 'Register connection' : 'Save changes'),
                        ['class' => 'btn btn-success']
                    ) ?>
                    <a href="<?= \yii\helpers\Url::to(['/connection/index']) ?>" class="btn btn-outline-secondary">Cancel</a>

                    <?php if (!$isNew): ?>
                        <!-- Test connection button (posts to /connection/test?id=X) -->
                        <form method="post" action="<?= \yii\helpers\Url::to(['/connection/test', 'id' => $model->id]) ?>" class="ms-auto">
                            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-plug me-1"></i>Test connection
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

            <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>