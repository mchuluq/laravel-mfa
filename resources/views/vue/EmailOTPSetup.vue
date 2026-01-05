<template>
    <div class="email-otp-setup">
        <!-- Setup Card -->
        <div class="card" v-if="!isConfigured">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-envelope"></i> Setup Verifikasi Email</span>
            </div>
            <div class="card-body">
                <div class="alert alert-info"><i class="icon fas fa-info-circle"></i> Aktifkan email verifikasi untuk menerima kode sekali pakai melalui email saat login.</div>
                <div class="callout callout-success">
                    <span><i class="fas fa-check"></i> Bagaimana cara kerjanya:</span>
                    <ul class="mb-0">
                        <li>Ketika Anda Login, kami akan mengirimkan kode sekali pakai ke alamat email Anda.</li>
                        <li>Masukkan kode untuk menyelesaikan login Anda.</li>
                        <li>Kode sekali pakai akan berlaku selama 10 menit.</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label>Alamat Email</label>
                    <input type="email" class="form-control" :value="userEmail" readonly disabled>
                    <small class="form-text text-muted">Kode akan dikirim ke alamat email ini.</small>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary btn-block" @click="enableEmailOTP" :disabled="loading">
                    <span v-if="loading"><i class="fas fa-spinner fa-spin"></i> Mengaktifkan...</span>
                    <span v-else><i class="fas fa-check-circle"></i> Aktifkan verifikasi email</span>
                </button>
            </div>
            <div class="overlay" v-if="loading">
                <i class="fas fa-2x fa-sync-alt fa-spin"></i>
            </div>
        </div>
        <!-- Management Card -->
        <div class="card" v-else>
            <div class="card-header">
                <span class="card-title"><i class="fas fa-envelope"></i> Email Code</span>
                <div class="card-tools">
                    <span class="badge badge-success"><i class="fas fa-check"></i> Dinyalakan</span>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-success"><i class="icon fas fa-check"></i> Verifikasi Email Code aktif dan siap digunakan.</div>
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-success"><i class="fas fa-envelope"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Verifikasi Email</span>
                        <span class="info-box-number text-sm">{{ data.email || userEmail }}</span>
                    </div>
                </div>
                <div v-if="data.last_sent_at" class="mb-3">
                    <small class="text-muted"><i class="far fa-clock"></i> Last code sent: {{ formatDate(data.last_sent_at) }}</small>
                </div>
                <div class="alert alert-success mt-3" v-if="testSent"><i class="icon fas fa-check"></i>Test code has been sent to your email!</div>
            </div>
            <div class="card-footer">
                <button class="btn btn-outline-primary btn-block" @click="sendTestCode" :disabled="loading || !canSendTest">
                    <span v-if="loading"><i class="fas fa-spinner fa-spin"></i> Mengirim...</span>
                    <span v-else-if="!canSendTest"><i class="fas fa-clock"></i> Tunngu {{ testCooldown }} detik untuk mengirim lagi</span>
                    <span v-else><i class="fas fa-paper-plane"></i> Kirim kode percobaan</span>
                </button>
            </div>
            <div class="overlay" v-if="loading">
                <i class="fas fa-2x fa-sync-alt fa-spin"></i>
            </div>
        </div>
    </div>
</template>
<script>
import moment from 'moment';
export default {
    name: 'EmailOTPSetup',
    props: {
        user: {
            type: Object,
            default: null
        }
    },
    data() {
        return {
            loading: false,
            isConfigured: false,
            data: null,
            testSent: false,
            testCooldown: 0,
            cooldownInterval: null
        };
    },
    computed: {
        userEmail() {
            return this.user?.email || '';
        },
        canSendTest() {
            return this.testCooldown === 0;
        }
    },
    methods: {
        async checkStatus() {
            try {
                const { data } = await this.$http.get('/mfa/email-otp');
                this.isConfigured = data.isConfigured;
                this.data = data.data;
            } catch (error) {
                console.error('Failed to get Email OTP status:', error);
            }
        },
        async enableEmailOTP() {
            try {
                this.loading = true;
                await this.$http.post('/mfa/email-otp');                
                new Noty({type: 'success',text: 'Verifikasi email berhasil diaktifkan!'}).show();
                this.$emit('complete');
                await this.checkStatus();
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'Gagal mengaktifkan verifikasi email'}).show();
            } finally {
                this.loading = false;
            }
        },
        async sendTestCode() {
            try {
                this.loading = true;
                this.testSent = false;
                await this.$http.post('/mfa/email-otp/test');                
                this.testSent = true;
                this.startCooldown(60); // 60 seconds cooldown                
                setTimeout(() => {
                    this.testSent = false;
                }, 5000);
            } catch (error) {
                new Noty({type: 'error',text: error.response?.data?.message || 'Gagal mengirim kode percobaan'}).show();
            } finally {
                this.loading = false;
            }
        },
        startCooldown(seconds) {
            this.testCooldown = seconds;            
            if (this.cooldownInterval) {
                clearInterval(this.cooldownInterval);
            }
            this.cooldownInterval = setInterval(() => {
                this.testCooldown--;                
                if (this.testCooldown <= 0) {
                    clearInterval(this.cooldownInterval);
                    this.cooldownInterval = null;
                }
            }, 1000);
        },
        formatDate(date) {
            return moment(date).fromNow();
        }
    },
    mounted() {
        this.checkStatus();
    },
    beforeUnmount() {
        if (this.cooldownInterval) {
            clearInterval(this.cooldownInterval);
        }
    }
};
</script>