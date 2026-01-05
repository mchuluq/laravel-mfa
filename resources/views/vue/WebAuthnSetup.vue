<template>
    <div class="webauthn-setup">
        <!-- Keys List Card -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-key"></i> Security Keys</span>
                <div class="card-tools">
                    <button class="btn btn-xs btn-primary" @click="showAddKeyModal = true" v-if="!showBrowserCheck"><i class="fas fa-plus"></i> Tambah Key</button>
                </div>
            </div>
            <!-- Browser Check -->
                <div class="px-2 py-3" v-if="showBrowserCheck">
                    <div class="alert alert-danger"><i class="icon fas fa-exclamation-triangle"></i><strong> Tidak didukung!</strong> Browser Anda tidak mendukung WebAuthn. harap menggunakan browser modern seperti Chrome, Firefox, atau Edge versi terbaru.</div>
                </div>
                <template v-else>
                    <!-- Empty State -->
                    <div class="text-center py-4" v-if="keys.length === 0 && !loading">
                        <i class="fas fa-key text-muted mb-3"></i>
                        <span>Tidak ada Security Key</span>
                        <span class="d-block text-muted mb-3">Tambah security key atau aktifkan autentikasi biometrik</span>
                        <button class="btn btn-primary" @click="showAddKeyModal = true"><i class="fas fa-plus-circle"></i> Tambahkan Security Key</button>
                    </div>
                    <!-- Keys List -->
                    <div class="list-group list-group-flush" v-else>
                        <div class="list-group-item" v-for="key in keys" :key="key.id">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="flex-grow-1">
                                <h6 class="mb-1"><i class="fas fa-key text-primary"></i> {{ key.name }}</h6>
                                <small class="text-muted">
                                    <i class="fas fa-fingerprint"></i> {{ key.authenticator_type }}<span class="mx-2">•</span><i class="fas fa-plug"></i> {{ key.transports }}
                                </small>
                                <br>
                                <small class="text-muted">
                                    <i class="far fa-clock"></i> Ditambahkan {{ formatDate(key.created_at) }}<span v-if="key.last_used_at"> &bull; Last used {{ formatDate(key.last_used_at) }}</span>
                                </small>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" @click="editKey(key)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(key)"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            <div class="overlay" v-if="loading">
                <i class="fas fa-2x fa-sync-alt fa-spin"></i>
            </div>
        </div>
        <!-- Info Card -->
        <div class="card" v-if="!showBrowserCheck">
            <div class="card-header"><span class="card-title"><i class="fas fa-info-circle"></i> Tentang Security Key</span></div>
            <div class="card-body">
                <p class="mb-2"><strong>Apa yang bisa saya gunakan ?</strong></p>
                <ul class="mb-3">
                    <li>Hardware security key (YubiKey, Titan, dll.)</li>
                    <li>Built-in biometrics (Face ID, Touch ID, Windows Hello)</li>
                    <li>Platform autentikator di perangkat anda</li>
                </ul>
                <p class="mb-0"><i class="fas fa-shield-alt text-success"></i> Security key memberikan keamanan tambahan untuk akun anda.</p>
            </div>
        </div>
        <!-- Add Key Modal -->
        <AddWebAuthnKeyModal :show="showAddKeyModal" @close="showAddKeyModal = false" @success="handleKeyAdded"/>
        <!-- Edit Key Modal -->
        <EditWebAuthnKeyModal :show="showEditKeyModal" :key-data="selectedKey" @close="showEditKeyModal = false" @success="handleKeyUpdated"/>
        <!-- Delete Confirmation Modal -->
        <DeleteWebAuthnKeyModal :show="showDeleteModal" :key-data="selectedKey" @close="showDeleteModal = false" @confirm="handleKeyDeleted"/>
    </div>
</template>

<script>
import moment from 'moment';
import AddWebAuthnKeyModal from './modals/AddWebAuthnKeyModal.vue';
import EditWebAuthnKeyModal from './modals/EditWebAuthnKeyModal.vue';
import DeleteWebAuthnKeyModal from './modals/DeleteWebAuthnKeyModal.vue';

export default {
    name: 'WebAuthnSetup',
    components: {
        AddWebAuthnKeyModal,
        EditWebAuthnKeyModal,
        DeleteWebAuthnKeyModal
    },
    props: {},
    data() {
        return {
            loading: false,
            keys: [],
            showAddKeyModal: false,
            showEditKeyModal: false,
            showDeleteModal: false,
            selectedKey: {},
            showBrowserCheck: false
        };
    },
    methods: {
        async loadKeys() {
            try {
                this.loading = true;
                const { data } = await this.$http.get('/mfa/webauthn');
                this.keys = data.data?.keys || [];
            } catch (error) {
                new Noty({type: 'error',text: 'Failed to load security keys'}).show();
            } finally {
                this.loading = false;
            }
        },
        checkBrowserSupport() {
            if (!window.PublicKeyCredential) {
                this.showBrowserCheck = true;
            }
        },
        editKey(key) {
            this.selectedKey = key;
            this.showEditKeyModal = true;
        },
        confirmDelete(key) {
            this.selectedKey = key;
            this.showDeleteModal = true;
        },
        handleKeyAdded() {
            this.showAddKeyModal = false;
            this.loadKeys();
            this.$emit('complete');
        },
        handleKeyUpdated() {
            this.showEditKeyModal = false;
            this.selectedKey = null;
            this.loadKeys();
        },
        handleKeyDeleted() {
            this.showDeleteModal = false;
            this.selectedKey = null;
            this.loadKeys();
        },
        formatDate(date) {
            return moment(date).fromNow();
        }
    },
    mounted() {
        this.checkBrowserSupport();
        this.loadKeys();
    }
};
</script>