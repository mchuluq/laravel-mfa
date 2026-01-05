<!-- AddWebAuthnKeyModal.vue -->
<template>
    <div>
        <div class="modal fade" :class="{ show: show }" :style="{ display: show ? 'block' : 'none' }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Security Key</h5>
                        <button type="button" class="close" @click="$emit('close')"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info" v-if="!waiting"><i class="icon fas fa-info-circle"></i>Click "Register" and follow your browser's prompts to add your security key or biometric.</div>
                        <div class="text-center py-4" v-if="waiting">
                            <div class="spinner-border text-primary mb-3" role="status"><span class="sr-only">Waiting...</span></div>
                            <p>Waiting for your authenticator...</p>
                            <small class="text-muted">Follow the prompts in your browser</small>
                        </div>
                        <div class="form-group" v-if="!waiting">
                            <label>Key Name</label>
                            <input type="text" class="form-control" v-model="keyName" placeholder="e.g., YubiKey, Touch ID">
                            <small class="form-text text-muted">Give your key a memorable name</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')" :disabled="waiting">Cancel</button>
                        <button type="button" class="btn btn-primary" @click="register" :disabled="!keyName || waiting"><i class="fas fa-fingerprint"></i> Register</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" :class="{ show: show }" v-if="show"></div>
    </div>
</template>
<script>
import { startRegistration } from '@simplewebauthn/browser';
export default {
    name: 'AddWebAuthnKeyModal',
    props: {
        show: Boolean,
    },
    data() {
        return {
            keyName: '',
            waiting: false
        };
    },
    methods: {
        async register() {
            try {
                this.waiting = true;
                // Get registration options from server
                const { data } = await this.$http.post('/mfa/webauthn/register/options');
                // Start WebAuthn registration
                const credential = await startRegistration(data.options.publicKey);
                // Register with server
                await this.$http.post('/mfa/webauthn/register', {
                    name: this.keyName,
                    credential
                });
                new Noty({type: 'success',text: 'Security key berhasil terdaftar'}).show();
                this.$emit('success');
                this.keyName = '';
            } catch (error) {
                if (error.name === 'NotAllowedError') {
                    new Noty({type: 'error',text: 'Registrasi dibatalkan atau timeout'}).show();
                } else {
                    new Noty({type: 'error',text: error.response?.data?.message || 'Registrasi gagal'}).show();
                }
            } finally {
                this.waiting = false;
            }
        }
    }
};
</script>