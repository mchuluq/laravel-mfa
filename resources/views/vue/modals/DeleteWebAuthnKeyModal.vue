<!-- DeleteWebAuthnKeyModal.vue -->
<template>
    <div>
        <div class="modal fade" :class="{ show: show }" :style="{ display: show ? 'block' : 'none' }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger">
                        <h5 class="modal-title text-white"><i class="fas fa-trash"></i> Hapus Security Key</h5>
                        <button type="button" class="close text-white" @click="$emit('close')"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda ingin menghapus security key : <strong>{{ keyData.name }}</strong>?</p>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" v-model="password" @keyup.enter="confirm">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Batal</button>
                        <button type="button" class="btn btn-danger" @click="confirm" :disabled="!password || loading"><i class="fas fa-trash"></i> Hapus</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" :class="{ show: show }" v-if="show"></div>
    </div>
</template>
<script>
export default {
    name: 'DeleteWebAuthnKeyModal',
    props: {
        show: Boolean,
        keyData: Object
    },
    data() {
        return {
            password: '',
            loading: false
        };
    },
    methods: {
        async confirm() {
            try {
                this.loading = true;
                await this.$http.delete(`/mfa/webauthn/${this.keyData.id}`, {data: { password: this.password }});
                new Noty({type: 'success',text: 'Security key berhasil dihapus!'}).show();
                this.$emit('confirm');
                this.password = '';
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'Gagal menghapus security key'}).show();
            } finally {
                this.loading = false;
            }
        }
    }
};
</script>