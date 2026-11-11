<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Update Threshold</h4>
                </div>
                <div class="modal-body">
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group" :class="{'has-error':errors.has('name')}">
                                <label>LC</label>
                                <input type="text" class="form-control" v-model="lc" v-validate="'required|decimal'" data-vv-name="lc">
                                <span class="text-danger" v-show="errors.has('lc')">Please enter LC.</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group" :class="{'has-error':errors.has('name')}">
                                <label>Threshold QTY</label>
                                <input type="text" class="form-control" v-model="threshold" v-validate="'required|decimal'" data-vv-name="threshold">
                                <span class="text-danger" v-show="errors.has('threshold')">Please enter Threshold QTY.</span>
                            </div>
                        </div>
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
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-primary" @click="process">UPDATE</button>
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
    Vue.use(VeeValidate)
    Validator.extend('phone',phoneValidator)
    export default {
        mixins : [appMixin],
        components:{
            Multiselect,
        },
        props:{

        },
        data(){
            return {
                lc: 0,
                threshold:0,
                uom : "SFT",
                record : {}
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
                this.loaded = false
                this.lc = 0
                this.threshold = 0
                this.uom = "SFT"
                this.record = Object.assign({},{})
            })
        },
        methods:{
            show(rec){
                console.log(rec)
                let self = this
                this.lc = rec.lc
                this.threshold = rec.threshold_qty
                this.uom = rec.uom

                self.record = Object.assign({},rec)
                $(self.$refs.modal).modal('show')
            },
            process(){
                let self = this
                EventBus.$emit('loader:show')
                Vue.axios.put(`companies/${this.record.company_id}/stock_threshold/${this.record.id}`,{
                    lc : this.lc,
                    threshold : this.threshold,
                    uom : this.uom
                })
                .then(response => {
                    EventBus.$emit('loader:hide')
                    self.$emit('threshold-updated',response.data)
                    $(self.$refs.modal).modal('hide')
                })
                .catch(error => {

                })
            },
        }
    }
</script>