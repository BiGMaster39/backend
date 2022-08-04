<?= $this->extend('layout/installer') ?>

<?= $this->section('content') ?>
<div class="container" id="app">
    <div class="container-stepper">
        <ul class="progressbar">
            <li class="active"><?php echo lang("Install.install_12"); ?></li>
            <li><?php echo lang("Install.install_13"); ?></li>
            <li><?php echo lang("Install.install_14"); ?></li>
            <li><?php echo lang("Install.install_15"); ?></li>
        </ul>
    </div>
    <div class="row">
        <div class="col-md-5 mx-auto">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="url" class="form-label">
                            <?php echo lang("Install.install_29"); ?>
                        </label>
                        <input type="text" class="form-control" id="url" placeholder="https://site.com/" v-model="url">
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <?php echo lang("Install.install_4"); ?>
                        </label>
                        <input type="text" class="form-control" id="name" placeholder="database_name" v-model="name">
                    </div>
                    <div class="mb-3">
                        <label for="hostname" class="form-label">
                            <?php echo lang("Install.install_5"); ?>
                        </label>
                        <input type="text" class="form-control" id="hostname" placeholder="hostname" value="hostname" v-model="hostname">
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <?php echo lang("Install.install_6"); ?>
                        </label>
                        <input type="text" class="form-control" id="username" placeholder="root" v-model="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <?php echo lang("Install.install_7"); ?>
                        </label>
                        <input type="password" class="form-control" id="password" placeholder="*******" v-model="password">
                    </div>
                    <div class="mb-3">
                        <label for="port" class="form-label">
                            <?php echo lang("Install.install_8"); ?>
                        </label>
                        <input type="text" class="form-control" id="port" placeholder="3306" v-model="port">
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
            url: "",
            name: "",
            hostname: "localhost",
            username: "",
            password: "",
            port: 3306,
            loading: false,
            error: {
                dialog: false,
                message: []
            }
        }),
        methods: {
            start() {
                this.loading = true;
                let params = new URLSearchParams();
                params.set('name', this.name);
                params.set('hostname', this.hostname);
                params.set('username', this.username);
                params.set('password', this.password);
                params.set('port', this.port);
                params.set('url', this.url);
                fetch('..//install/db_install', {method: 'POST', body: params})
                    .then((response) => {
                        this.loading = false;
                        response.json().then(data => {
                            console.log(data);
                            if (data.code === 200) {
                                window.location.href = 'step_2';
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