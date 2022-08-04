<?= $this->extend('layout/installer') ?>

<?= $this->section('content') ?>
<div class="container" id="app">
    <div class="container-stepper">
        <ul class="progressbar">
            <li class="active"><?php echo lang("Install.install_12"); ?></li>
            <li class="active"><?php echo lang("Install.install_13"); ?></li>
            <li class="active"><?php echo lang("Install.install_14"); ?></li>
            <li class="active"><?php echo lang("Install.install_15"); ?></li>
        </ul>
    </div>
    <div class="row">
        <div class="col-md-5 mx-auto">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <img src="../theme/bootstrap/img/success.svg" alt="" width="40" height="40">
                        <div class="mt-2">
                            <strong><?php echo lang("Install.install_26"); ?></strong>
                        </div>
                    </div>
                    <div class="mb-3 text-center">
                        <p><?php echo lang("Install.install_27"); ?></p>
                    </div>
                </div>
            </div>
            <div class="alert alert-danger" role="alert">
                <span><?php echo lang("Install.install_28"); ?></span>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
