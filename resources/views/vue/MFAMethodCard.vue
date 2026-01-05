<template>
    <div class="card-body">
        <div class="d-flex w-100 justify-content-between">
            <h3 class="card-title"><i :class="icon"></i> {{ title }}</h3>
            <div class="card-tools">
                <span class="badge badge-success" v-if="enabled"><i class="fas fa-check"></i> Diaktifkan</span>
                <span class="badge badge-primary" v-if="isPrimary"><i class="fas fa-star"></i> Utama</span>
            </div>
        </div>
        <div class="form-group">
            <div class="text-muted d-block">{{ description }}</div>
            <div v-if="enabled && lastUsed" class="small text-muted"><i class="far fa-clock"></i> Terakhir dipakai: {{ formatDate(lastUsed) }}</div>            
        </div>
        <div class="d-block">
            <div class="btn-group btn-block" v-if="!enabled">
                <button class="btn btn-sm btn-primary btn-block" @click="$emit('setup')"><i class="fas fa-plus-circle"></i> Aktifkan</button>
            </div>
            <div v-else>
                <div class="btn-group btn-block mb-2">
                    <button class="btn btn-sm btn-outline-primary" @click="$emit('setup')"><i class="fas fa-cog"></i> Atur</button>
                    <button class="btn btn-sm btn-outline-primary" @click="$emit('set-primary')" :disabled="isPrimary" v-if="!isPrimary"><i class="fas fa-star"></i> Gunakan Sebagai Utama</button>
                    <button class="btn btn-sm btn-outline-danger" @click="$emit('disable')"><i class="fas fa-times"></i> Matikan</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import moment from 'moment';
    export default {
    name: 'MFAMethodCard',
    props: {
        driver: {
            type: String,
            required: true
        },
        title: {
            type: String,
            required: true
        },
        description: {
            type: String,
            required: true
        },
        icon: {
            type: String,
            required: true
        },
        enabled: {
            type: Boolean,
            default: false
        },
        isPrimary: {
            type: Boolean,
            default: false
        },
        lastUsed: {
            type: String,
            default: null
        }
    },
    methods: {
        formatDate(date) {
            return moment(date).fromNow();
        }
    }
};
</script>