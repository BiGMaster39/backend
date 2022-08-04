<?= $this->extend('layout/installer') ?>

<?= $this->section('content') ?>
<div class="container" id="app">
    <div class="container-stepper">
        <ul class="progressbar">
            <li class="active"><?php echo lang("Install.install_12"); ?></li>
            <li class="active"><?php echo lang("Install.install_13"); ?></li>
            <li><?php echo lang("Install.install_14"); ?></li>
            <li><?php echo lang("Install.install_15"); ?></li>
        </ul>
    </div>
    <div class="row">
        <div class="col-md-5 mx-auto">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <img src="../theme/bootstrap/img/github.svg" alt="" width="40" height="40">
                        <div class="mt-2">
                            <strong><?php echo lang("Install.install_16"); ?></strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="git_username" class="form-label">
                            <?php echo lang("Install.install_21"); ?>
                        </label>
                        <input type="text" class="form-control" id="git_username" v-model="git_username">
                    </div>
                    <div class="mb-3">
                        <label for="git_token" class="form-label">
                            <?php echo lang("Install.install_18"); ?>
                        </label>
                        <input type="text" class="form-control" id="git_token" v-model="git_token">
                    </div>
                    <div class="mb-3">
                        <label for="git_repo" class="form-label">
                            <?php echo lang("Install.install_19"); ?>
                        </label>
                        <input type="text" class="form-control" id="git_repo" v-model="git_repo">
                    </div>
                    <div class="mb-3 mt-4 text-center">
                        <img src="../theme/bootstrap/img/codemagic.svg" alt="" width="40" height="40">
                        <div class="mt-2">
                            <strong><?php echo lang("Install.install_17"); ?></strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="cm_token" class="form-label">
                            <?php echo lang("Install.install_18"); ?>
                        </label>
                        <input type="text" class="form-control" id="cm_token" v-model="cm_token">
                    </div>
                    <div class="mb-3">
                        <label for="cm_id" class="form-label">
                            <?php echo lang("Install.install_20"); ?>
                        </label>
                        <input type="text" class="form-control" id="cm_id" v-model="cm_id">
                    </div>
                    <div v-if="error.dialog" class="alert alert-danger" role="alert">
                        <span v-for="(item, index) in error.message" :key="'error_' + index">
                            {{ item }}<br/>
                        </span>
                    </div>
                    <button type="button" class="btn btn-primary w-100" @click="start">
                        <span v-if="loading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span v-else>
                            <?php echo lang("Install.install_10"); ?>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
        var app = new Vue({
            el: "#app",
            data: () => ({
                loading: false,
                error: {
                    dialog: false,
                    message: []
                },
                git_username: "",
                git_token: "",
                git_repo: "",
                cm_token: "",
                cm_id: ""
            }),
            methods: {
                start() {
                    this.loading = true;
                    let params = new URLSearchParams();
                    params.set('git_username', this.git_username);
                    params.set('git_token', this.git_token);
                    params.set('git_repo', this.git_repo);
                    params.set('cm_token', this.cm_token);
                    params.set('cm_id', this.cm_id);
                    fetch('../install/api_connect', {method: 'POST', body: params})
                        .then((response) => {
                            this.loading = false;
                            response.json().then(data => {
                                if (data.code === 200) {
                                    window.location.href = 'step_3';
                                } else {
                                    if (data.code !== 500) {
                                        this.error = {
                                            dialog: true,
                                            message: data.message
                                        };
                                    } else {
                                        this.error = {
                                            dialog: true,
                                            message: ["<?php echo lang("Message.message_63"); ?>"]
                                        };
                                    }
                                }
                            });
                        });
                }
            },
        });
    </script>
<?= $this->endSection() ?>