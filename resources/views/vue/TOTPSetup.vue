<template>
    <div class="totp-setup">
        <!-- Setup Card -->
        <div class="card" v-if="!isConfigured">
            <div class="card-header">
                <span class="card-title">
                <i class="fas fa-mobile-alt"></i> Setup Authenticator App</span>
            </div>
            <div class="card-body">
                <!-- Step Indicator -->
                <div class="progress mb-3">
                    <div class="progress-bar" :style="{ width: progressWidth }"></div>
                </div>
                <!-- Step 1: Scan QR Code -->
                <div v-if="step === 1">
                    <span class="mb-3">Langkah 1: Pindai QR Code</span>
                    <div class="alert alert-info">
                        <i class="icon fas fa-info-circle"></i> Pindai QRCode ini menggunakan aplikasi autentikator (Google Authenticator, Authy, dll.)
                    </div>
                    <div class="text-center mb-3">
                        <div v-html="qrCode" v-if="qrCode"></div>
                        <div class="spinner-border" role="status" v-else>
                        <span class="sr-only">Menunggu...</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Atau masukkan kunci ini secara manual:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" :value="secret" readonly >
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" @click="copySecret" >
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-block" @click="nextStep" :disabled="!qrCode">Next <i class="fas fa-arrow-right"></i></button>
                </div>

                <!-- Step 2: Verify Code -->
                <div v-if="step === 2">
                    <span class="mb-3">Langkah 2: Verifikasi Kode</span>
                    <div class="alert alert-info"><i class="icon fas fa-info-circle"></i> Masukkan 6-digit kode angka yang ditampilkan di aplikasi autentikator Anda</div>
                    <div class="form-group">
                        <label>Kode verifikasi</label>
                        <input type="text" class="form-control form-control-lg text-center" style="letter-spacing: 8px; font-size: 24px;" v-model="verificationCode" maxlength="6" placeholder="000000" @input="handleCodeInput" ref="codeInput" >
                        <small class="form-text text-danger" v-if="error">{{ error }}</small>
                    </div>
                    <div class="btn-group btn-block">
                        <button class="btn btn-outline-secondary" @click="prevStep" :disabled="loading" >
                            <i class="fas fa-arrow-left"></i> Kembali
                        </button>
                        <button class="btn btn-primary" @click="verifyCode" :disabled="loading || verificationCode.length !== 6" >
                            <span v-if="loading"> <i class="fas fa-spinner fa-spin"></i> Memverifikasi... </span>
                            <span v-else> <i class="fas fa-check"></i> Verifikasi </span>
                        </button>
                    </div>
                </div>
                <!-- Step 3: Backup Codes -->
                <div v-if="step === 3">
                    <span class="mb-3">Langkah 3: Simpan Kode Cadangan</span>
                    <div class="alert alert-warning"><i class="icon fas fa-exclamation-triangle"></i><strong>Penting!</strong> Simpan kode cadangan ini di tempat aman.  Anda dapat menggunakan kode cadangan untuk mengakses akun Anda jika perangkat Anda hilang. </div>
                    <div class="card bg-light">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6" v-for="(code, index) in backupCodes" :key="index"><code class="d-block mb-2">{{ code }}</code></div>
                            </div>
                        </div>
                    </div>
                    <div class="btn-group btn-block mt-3">
                        <button class="btn btn-outline-primary" @click="downloadBackupCodes"> <i class="fas fa-download"></i> Download</button>
                        <button class="btn btn-outline-primary" @click="copyBackupCodes"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                    <button class="btn btn-success btn-block mt-3" @click="finish"><i class="fas fa-check-circle"></i> Selesaikan Setup</button>
                </div>
            </div>
            <div class="overlay" v-if="loading">
                <i class="fas fa-2x fa-sync-alt fa-spin"></i>
            </div>
        </div>
        <!-- Management Card -->
        <div v-else>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-mobile-alt"></i> Authenticator App</span>
                    <div class="card-tools">
                        <span class="badge badge-success"><i class="fas fa-check"></i> Dinyalakan</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-success"><i class="icon fas fa-check"></i> Aplikasi Autentikator anda dinyalakan dan bekerja.</div>
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-shield-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Kode cadangan tersisa</span>
                            <span class="info-box-number">{{ data.remaining_backup_codes || 0 }}</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-6" v-for="(code, index) in backupCodes" :key="index"><code class="d-block mb-2">{{ code }}</code></div>
                        </div>
                    </div>
                    <div class="btn-group btn-block">
                        <!-- <button class="btn btn-outline-primary" @click="$emit('view-backup-codes')"><i class="fas fa-list"></i> View Backup Codes</button> -->
                        <button class="btn btn-outline-primary" @click="viewBackupCodes"><i class="fas fa-list"></i> Lihat Kode Cadangan</button>
                        <button class="btn btn-outline-primary" @click="downloadBackupCodes" v-if="backupCodes"> <i class="fas fa-download"></i> Unduh</button>
                        <button class="btn btn-outline-warning" @click="showRegenerateModal = true"><i class="fas fa-sync"></i> generate ulang</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Regenerate Backup Codes Modal -->
        <RegenerateBackupCodesModal :show="showRegenerateModal" @close="showRegenerateModal = false" @confirm="handleRegenerate"/>
    </div>
</template>
<script>
import RegenerateBackupCodesModal from './modals/RegenerateBackupCodesModal.vue';
export default {
    name: 'TOTPSetup',
    components: {
        RegenerateBackupCodesModal
    },
    props: {},
    data() {
        return {
            loading: false,
            step: 1,
            qrCode: null,
            secret: null,
            backupCodes: [],
            verificationCode: '',
            error: null,
            isConfigured: false,
            data: null,
            showRegenerateModal: false
        };
    },
    computed: {
        progressWidth() {
            return `${(this.step / 3) * 100}%`;
        }
    },
    methods: {
        async loadSetup() {
            try {
                this.loading = true;
                const { data } = await this.$http.get('/mfa/totp/setup');
                // console.log("ini data : ",data);                
                this.qrCode = data.qrCode;
                this.secret = data.secret;
                this.backupCodes = data.backupCodes;
            } catch (error) {
                console.log(error);
                new Noty({type: 'error',text: error.response?.data?.message || 'Failed to setup TOTP'}).show();
            } finally {
                this.loading = false;
            }
        },
        async checkStatus() {
            try {
                const { data } = await this.$http.get('/mfa/totp');
                this.isConfigured = data.isConfigured;
                this.data = data.data;
            } catch (error) {
                console.error('Failed to get TOTP status:', error);
            }
        },
        async viewBackupCodes(){
            try {
                const { data } = await this.$http.get('/mfa/totp/backup-codes');
                this.backupCodes = data.backupCodes;
            } catch (error) {
                console.error('Failed to get TOTP status:', error);
            }
        },
        nextStep() {
            if (this.step < 3) {
                this.step++;
                if (this.step === 2) {
                    this.$nextTick(() => {
                        this.$refs.codeInput?.focus();
                    });
                }
            }
        },
        prevStep() {
            if (this.step > 1) {
                this.step--;
                this.error = null;
            }
        },
        handleCodeInput() {
            this.verificationCode = this.verificationCode.replace(/[^0-9]/g, '');
            this.error = null;
        },
        async verifyCode() {
            try {
                this.loading = true;
                this.error = null;
                await this.$http.post('/mfa/totp/setup', {
                    code: this.verificationCode
                });
                new Noty({type: 'success',text: 'Authenticator app enabled successfully!'}).show();
                this.nextStep();
            } catch (error) {
                this.error = error.response?.data?.message || 'Invalid verification code';
            } finally {
                this.loading = false;
            }
        },
        copySecret() {
            navigator.clipboard.writeText(this.secret);
            new Noty({type: 'success',text: 'Kunci rahasia disalin ke clipboard!'}).show();
        },
        copyBackupCodes() {
            const text = this.backupCodes.join('\n');
            navigator.clipboard.writeText(text);
            new Noty({type: 'success',text: 'Kode cadangan disalin ke clipboard!'}).show();
        },
        downloadBackupCodes() {
            const text = this.backupCodes.join('\n');
            const blob = new Blob([text], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const app_name = document.getElementsByName('app_name')[0].value;
            a.href = url;
            a.download = `mfa-backup-codes-${app_name}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        },
        finish() {
            this.$emit('complete');
            this.checkStatus();
        },
        async handleRegenerate(password) {
            try {
                this.loading = true;
                const { data } = await this.$http.post('/mfa/totp/backup-codes/regenerate', {password});
                new Noty({type: 'success',text: 'Kode Cadangan berhasil dibuat ulang!'}).show();
                this.showRegenerateModal = false;                
                // Show new codes
                this.backupCodes = data.backup_codes;
                this.step = 3;
                this.isConfigured = false;
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'Failed to regenerate backup codes'}).show();
            } finally {
                this.loading = false;
            }
        }
    },
    mounted() {
        this.checkStatus();
        if (!this.isConfigured) {
            this.loadSetup();
        }
    }
};
</script>