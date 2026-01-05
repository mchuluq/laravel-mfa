<!-- EditWebAuthnKeyModal.vue -->
<template>
    <div>
        <div class="modal fade" :class="{ show: show }" :style="{ display: show ? 'block' : 'none' }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Ubah Security Key</h5>
                        <button type="button" class="close" @click="$emit('close')"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>nama perangkat/kunci</label>
                            <input type="text" class="form-control" v-model="name" placeholder="e.g., YubiKey, Touch ID">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Batal</button>
                        <button type="button" class="btn btn-primary" @click="save" :disabled="!name || loading"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" :class="{ show: show }" v-if="show"></div>
    </div>
</template>
<script>
export default {
    name: 'EditWebAuthnKeyModal',
    props: {
        show: Boolean,
        keyData: Object
    },
    data() {
        return {
            name: '',
            loading: false
        };
    },
    methods: {
        async save() {
            try {
                this.loading = true;
                await this.$http.patch(`/mfa/webauthn/${this.keyData.id}`, {
                    name: this.name
                });
                new Noty({type: 'success',text: 'Security key berhasil diubah!'}).show();
                this.$emit('success');
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'Gagal mengubah security key'}).show();
            } finally {
                this.loading = false;
            }
        }
    },
    watch: {
        keyData: {
            immediate: true,
            handler(val) {
                if (val) {
                this.name = val.name;
                }
            }
        }
    }
};
</script>