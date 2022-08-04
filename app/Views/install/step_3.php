<?= $this->extend('layout/installer') ?>

<?= $this->section('content') ?>
<div class="container" id="app">
    <div class="container-stepper">
        <ul class="progressbar">
            <li class="active"><?php echo lang("Install.install_12"); ?></li>
            <li class="active"><?php echo lang("Install.install_13"); ?></li>
            <li class="active"><?php echo lang("Install.install_14"); ?></li>
            <li><?php echo lang("Install.install_15"); ?></li>
        </ul>
    </div>
    <div class="row">
        <div class="col-md-5 mx-auto">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <?php echo lang("Install.install_23"); ?>
                        </label>
                        <input type="text" class="form-control" id="email" v-model="email">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <?php echo lang("Install.install_24"); ?>
                        </label>
                        <input type="password" class="form-control" id="password" v-model="password">
                    </div>
                    <div class="mb-3">
                        <label for="re_password" class="form-label">
                            <?php echo lang("Install.install_25"); ?>
                        </label>
                        <input type="password" class="form-control" id="re_password" v-model="re_password">
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
            email: "",
            password: "",
            re_password: "",
        }),
        methods: {
            start() {
                this.loading = true;
                let params = new URLSearchParams();
                params.set('email', this.email);
                params.set('password', this.password);
                params.set('re_password', this.re_password);
                fetch('../install/create_admin', {method: 'POST', body: params})
                    .then((response) => {
                        this.loading = false;
                        response.json().then(data => {
                            if (data.code === 200) {
                                window.location.href = 'finish';
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
