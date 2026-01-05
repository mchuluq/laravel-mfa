<template>
    <div class="mfa-dashboard">
        <!-- Header Card -->
        <div class="card">
            <div class="card-header"><i class="fas fa-shield-alt fa-fw"></i>Autentikasi 2 faktor</div>
            <div class="card-body">
                <div class="alert alert-info mb-0" v-if="!hasMFAEnabled"><i class="icon fas fa-info"></i><strong> Tingkatkan keamanan!</strong> Autentikasi 2 faktor memberikan keamanan tambahan untuk akun Anda.</div>
                <div class="alert alert-success mb-0" v-else><i class="icon fas fa-check"></i><strong>Terlindungi!</strong> Akun anda diamankan dengan autentikasi 2 faktor.</div>
                
                <div class="alert alert-success mt-2 mb-0" v-if="statistics && statistics.is_device_remembered"><i class="icon fas fa-bookmark"></i>Perangkat ini tersimpan</div>
            </div>
            <div class="px-3">
                <ul class="nav nav-tabs">
                    <li class="nav-item"><a class="nav-link active" href="#tab-totp" data-toggle="tab">Authenticator App</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tab-mail-otp" data-toggle="tab">Email Code</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tab-webauthn" data-toggle="tab">Security Key</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tab-disable" data-toggle="tab" v-if="hasMFAEnabled"><i class="fas fa-power-off text-danger"></i></a></li>
                </ul>
            </div>
            <div class="tab-content">
                    <div class="tab-pane active" id="tab-totp">
                        <MFAMethodCard driver="totp" title="Authenticator App" description="Gunakan Aplikasi seperti google authenticator atau authy" icon="fa fa-mobile-alt" :enabled="isMethodEnabled('totp')" :is-primary="isMethodPrimary('totp')" :last-used="getMethodLastUsed('totp')" @setup="$emit('show-setup', 'totp')" @disable="handleDisable('totp')" @set-primary="handleSetPrimary('totp')" />
                    </div>
                    <div class="tab-pane" id="tab-mail-otp">
                        <MFAMethodCard driver="email_otp" title="Email Code" description="Terima kode verifikasi melalui email" icon="fa fa-envelope" :enabled="isMethodEnabled('email_otp')" :is-primary="isMethodPrimary('email_otp')" :last-used="getMethodLastUsed('email_otp')" @setup="$emit('show-setup', 'email_otp')" @disable="handleDisable('email_otp')" @set-primary="handleSetPrimary('email_otp')" />
                    </div>
                    <div class="tab-pane" id="tab-webauthn">
                        <MFAMethodCard driver="webauthn" title="Security Key" description="Gunakan kunci hardware atau autentikasi biometrik" icon="fa fa-key" :enabled="isMethodEnabled('webauthn')" :is-primary="isMethodPrimary('webauthn')" :last-used="getMethodLastUsed('webauthn')" @setup="$emit('show-setup', 'webauthn')" @disable="handleDisable('webauthn')" @set-primary="handleSetPrimary('webauthn')" />
                    </div>
                    <div class="tab-pane" id="tab-disable" v-if="hasMFAEnabled">
                        <div class="card-body">
                            <p class="d-block">Mematikan semua metode autentikasi 2 faktor akan mengurangi keamanan akun Anda.</p>
                            <div class="d-block">
                                <button class="btn btn-danger btn-block" @click="showDisableAllModal = true" :disabled="loading"><i class="fas fa-times-circle"></i> Matikan Semua</button>
                            </div>
                        </div>
                    </div>
                </div>
            <div class="card-footer" v-if="statistics">
                <div class="row">
                    <div class="col-6 col-md-3 border-right">
                        <div class="description-block">
                            <h6 class="description-header">{{statistics.total_methods}}</h6>
                            <span class="description-text">Total metode</span>
                        </div>                        
                    </div>
                    <div class="col-6 col-md-3 border-right">
                        <div class="description-block">
                            <h6 class="description-header">{{ statistics.enabled_methods }}</h6>
                            <span class="description-text">Diaktifkan</span>
                        </div>                        
                    </div>
                    <div class="col-6 col-md-3 border-right">
                        <div class="description-block">
                            <h6 class="description-header">{{ primaryMethodName }}</h6>
                            <span class="description-text">Metode utama</span>
                        </div>                        
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="description-block">
                            <h6 class="description-header">{{ lastUsedFormatted }}</h6>
                            <span class="description-text">Terakhir digunakan</span>
                        </div>                        
                    </div>
                </div>
            </div>
            <!-- Loading Overlay -->
            <div class="overlay" v-if="loading"><i class="fas fa-2x fa-sync-alt fa-spin"></i></div>
        </div>
        <!-- Disable All Modal -->
        <DisableAllModal :show="showDisableAllModal" @close="showDisableAllModal = false" @confirm="handleDisableAll" />
        <!-- Confirm Password Modal -->
        <ConfirmPasswordModal :show="showPasswordModal" :title="passwordModalTitle" @close="showPasswordModal = false" @confirm="handlePasswordConfirm"/>
    </div>
</template>

<script>
import MFAMethodCard from './MFAMethodCard.vue';
import DisableAllModal from './modals/DisableAllModal.vue';
import ConfirmPasswordModal from './modals/ConfirmPasswordModal.vue';
import moment from 'moment';

export default {
    name: 'MFADashboard',
    components: {
        MFAMethodCard,
        DisableAllModal,
        ConfirmPasswordModal
    },
    props: {},
    data() {
        return {
            loading: false,
            statistics: null,
            showDisableAllModal: false,
            showPasswordModal: false,
            passwordModalTitle: '',
            pendingAction: null
        };
    },
    computed: {
        hasMFAEnabled() {
            return this.statistics?.enabled_methods > 0;
        },
        primaryMethodName() {
            const method = this.statistics?.methods?.find(m => m.is_primary);
            return method?.name || 'None';
        },
        lastUsedFormatted() {
            if (!this.statistics?.last_used) return 'Never';
            return moment(this.statistics.last_used).fromNow();
        }
    },
    methods: {
        async fetchStatistics() {
            try {
                this.loading = true;
                const { data } = await this.$http.get('/mfa/management/statistics');
                this.statistics = data.statistics;
            } catch (error) {
                console.log(error);
            } finally {
                this.loading = false;
            }
        },
        isMethodEnabled(driver) {
            return this.statistics?.methods?.some(m => m.driver === driver && m.is_enabled);
        },
        isMethodPrimary(driver) {
            return this.statistics?.methods?.some(m => m.driver === driver && m.is_primary);
        },
        getMethodLastUsed(driver) {
            const method = this.statistics?.methods?.find(m => m.driver === driver);
            return method?.last_used_at;
        },
        async handleSetPrimary(driver) {
            try {
                this.loading = true;
                await this.$http.post('/mfa/management/primary', { driver });            
                new Noty({type: 'success',text: 'Metode utama berhasil diubah!'}).show();
                await this.fetchStatistics();
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'Failed to set primary method'}).show();
            } finally {
                this.loading = false;
            }
        },
        handleDisable(driver) {
            this.pendingAction = { type: 'disable', driver };
            this.passwordModalTitle = `Disable ${this.getMethodTitle(driver)}`;
            this.showPasswordModal = true;
        },
        async handlePasswordConfirm(password) {
            if (this.pendingAction?.type === 'disable') {
                await this.executeDisable(this.pendingAction.driver, password);
            }
            this.showPasswordModal = false;
            this.pendingAction = null;
        },
        async executeDisable(driver, password) {
            try {
                this.loading = true;
                await this.$http.delete(`/mfa/management/${driver}/disable`, {data: { password }});
                new Noty({type: 'success',text: 'Metode MFA berhasil dinonaktifkan!'}).show();
                await this.fetchStatistics();
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'Gagal menon-aktifkan metode MFA'}).show();
            } finally {
                this.loading = false;
            }
        },
        async handleDisableAll(data) {
            try {
                this.loading = true;
                await this.$http.delete('/mfa/management/disable-all', {data});
                new Noty({type: 'success',text: 'Semua metode MFA berhasil dinonaktifkan!'}).show();
                await this.fetchStatistics();
                this.showDisableAllModal = false;
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'gagal menon-aktifkan semua metode MFA'}).show();
            } finally {
                this.loading = false;
            }
        },
        getMethodTitle(driver) {
            const titles = {
                totp: 'Authenticator App',
                email_otp: 'Email Code',
                webauthn: 'Security Key'
            };
            return titles[driver] || driver;
        }
    },
    mounted() {
        this.fetchStatistics();
    }
};
</script>