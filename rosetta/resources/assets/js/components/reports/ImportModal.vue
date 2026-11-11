<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Import Threshold</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-error':errors.has('name')}">
                                <label>Tenant</label>
                                <tenant :company="company" v-model="tenant"></tenant>
                                <!--<input type="text" class="form-control" v-model="lc" v-validate="'required|decimal'" data-vv-name="lc">
                                <span class="text-danger" v-show="errors.has('lc')">Please enter LC.</span>-->
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-error':errors.has('name')}">
                                <label>Stockpoint</label>
                                <stock-point v-model="stockPoint" :company="company" :tenant="tenant"></stock-point>
                                <span class="text-danger" v-show="errors.has('threshold')">Please enter Threshold QTY.</span>
                            </div>
                        </div>
                        <!--
                        <div class="col-md-3">
                            <div class="form-group" :class="{'has-error':errors.has('name')}">
                                <label>UOM</label>
                                 <multiselect
                                    v-model="uom" 
                                    :options="['SFT','SQM']"
                                    :show-labels="false"
                                ></multiselect>
                            </div>
                        </div>
                        -->
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group" :class="{'has-error':errors.has('file')}">
                                <label>File</label>
                                <input type="file" ref="file" v-validate="'required|size:2048|mimes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel'" data-vv-name="file" accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                <span class="text-danger" v-show="errors.has('file')">Please select a file.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-primary" @click="process">IMPORT</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import Vue from 'vue'
    import appMixin from '../../vuex-mixin.js'
    import {EventBus} from '../../configs/EventBus'
    import phoneValidator from '../../validators/phoneNumber.js'
    import VeeValidate from 'vee-validate'
    import { Validator } from 'vee-validate';
    import Multiselect from 'vue-multiselect'
    import tenant from './selects/TenantSelect'
    import stockPoint from './selects/StockPointSelect'

    Vue.use(VeeValidate)
    Validator.extend('phone',phoneValidator)
    export default {
        mixins : [appMixin],
        components:{
            Multiselect,
            tenant,
            stockPoint
        },
        props:{
            company:{
                required: true,
                type: Object
            }
        },
        data(){
            return {
                tenant: "",
                file : null,
                stockPoint : null,
            }
        },
        mounted(){
            let self = this
            $(this.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
            
            $(this.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.$refs.file.value = null
            })
        },
        methods:{
            show(){
                let self = this
                $(self.$refs.modal).modal('show')
            },
            hide(){
                let self = this
                $(this.$refs.modal).modal('hide')
            },
            process(){
                let self = this
                this.errors.clear()
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        let file = self.$refs.file.files[0]
                        let data = new FormData()
                        data.append('file',file)
                        data.append('stockpoint',self.stockPoint.id)
                        EventBus.$emit('loader:show')
                        Vue.axios.post(`companies/${this.$route.params.id}/stock_threshold/${this.tenant}/import`,data)
                        .then(response  => {
                            EventBus.$emit('loader:hide')
                            self.$emit('threshold-updated',true)
                        })
                        .catch(error => {

                        })
                        //
                    }
                })
                .catch(e => {

                })
                
                
            }
        }
    }
</script>