<!-- RegenerateBackupCodesModal.vue -->
<template>
    <div>
        <div class="modal fade" :class="{ show: show }" :style="{ display: show ? 'block' : 'none' }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-sync"></i> Generate ulang kode cadangan</h5>
                        <button type="button" class="close" @click="$emit('close')"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning"><strong>Peringatan!</strong> ini akan menghapus semua kode cadangan Anda sebelumnya.</div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" v-model="password" @keyup.enter="confirm">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Batal</button>
                        <button type="button" class="btn btn-warning" @click="confirm" :disabled="!password">Generate ulang</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" :class="{ show: show }" v-if="show"></div>
    </div>
</template>
<script>
export default {
    name: 'RegenerateBackupCodesModal',
    props: {
      	show: Boolean
    },
    data() {
		return {
			password: ''
		};
    },
    methods: {
		confirm() {
			if (this.password) {
				this.$emit('confirm', this.password);
				this.password = '';
			}
		}
    }
};
</script>