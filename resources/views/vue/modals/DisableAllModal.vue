<!-- DisableAllModal.vue -->
<template>
    <div>
        <div class="modal fade" :class="{ show: show }" :style="{ display: show ? 'block' : 'none' }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger">
                        <h5 class="modal-title text-white"><i class="fas fa-exclamation-triangle"></i> Non-aktifkan Semua Metode MFA?</h5>
                        <button type="button" class="close text-white" @click="$emit('close')"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger"><strong>Peringatan!</strong> ini akan non-aktifkan semua metode MFA Anda.</div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" v-model="password" placeholder="Enter your password">
                        </div>
                        <div class="form-group">
                            <label>ketik "DISABLE" untuk konfirmasi</label>
                            <input type="text" class="form-control" v-model="confirmation" placeholder="DISABLE">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
                        <button type="button" class="btn btn-danger"  @click="confirm" :disabled="!isValid">Non-aktifkan semua metode</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" :class="{ show: show }" v-if="show"></div>
    </div>
</template>
<script>
export default {
    name: 'DisableAllModal',
    props: {
        show: Boolean
    },
    data() {
        return {
            password: '',
            confirmation: ''
        };
    },
    computed: {
        isValid() {
            return this.password && this.confirmation === 'DISABLE';
        }
    },
    methods: {
        confirm() {
            if (this.isValid) {
                this.$emit('confirm', {password: this.password,confirmation: this.confirmation});
                this.password = '';
                this.confirmation = '';
            }
        }
    }
};
</script>