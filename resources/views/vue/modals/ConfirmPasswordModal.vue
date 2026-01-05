<!-- ConfirmPasswordModal.vue -->
<template>
    <div>
        <div class="modal fade" :class="{ show: show }" :style="{ display: show ? 'block' : 'none' }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ title }}</h5>
                        <button type="button" class="close" @click="$emit('close')"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p>Masukkan password untuk melanjutkan.</p>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" v-model="password" @keyup.enter="confirm" ref="passwordInput">
                            <small class="text-danger" v-if="error">{{ error }}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Batal</button>
                        <button type="button" class="btn btn-primary" @click="confirm" :disabled="!password">Konfirmasi</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" :class="{ show: show }" v-if="show"></div>
    </div>
</template>
<script>
export default {
    name: 'ConfirmPasswordModal',
    props: {
        show: Boolean,
        title: { type: String, default: 'Konfirmasi Password' }
    },
    data() {
        return {
            password: '',
            error: null
        };
    },
    methods: {
        confirm() {
            if (!this.password) {
                this.error = 'Password wajib diisi';
                return;
            }
            this.$emit('confirm', this.password);
            this.password = '';
            this.error = null;
        }
    },
    watch: {
        show(val) {
            if (val) {
                this.$nextTick(() => this.$refs.passwordInput?.focus());
            }
        }
    }
};
</script>