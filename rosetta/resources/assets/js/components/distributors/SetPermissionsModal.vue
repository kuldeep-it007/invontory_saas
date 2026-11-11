<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" v-if="distributor">{{distributor.name}} ({{distributor.nav_id}})</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="form-group">
                                    <div class="col-md-12">
                                        <div class="form-group text-right">
                                            <a href="#" class="text-green" @click.prevent="allowAll">Allow All</a>
                                            <a href="#" class="text-red" style="margin-left: 20px;" @click.prevent="denyAll">Deny All</a>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Add To Cart</label>
                                            <div class="form-group">
                                                <input type="radio" v-model="deny_add_to_cart" :value="false">  Allow &nbsp;&nbsp;
                                                <input type="radio" v-model="deny_add_to_cart" :value="true">  Deny &nbsp;&nbsp;
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Block Items</label>
                                            <div class="form-group">
                                                <input type="radio" v-model="deny_block" :value="false">  Allow &nbsp;&nbsp;
                                                <input type="radio" v-model="deny_block" :value="true">  Deny &nbsp;&nbsp;
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Place Advance Orders</label>
                                            <div class="form-group">
                                                <input type="radio" v-model="deny_advance_ordering" :value="false">  Allow &nbsp;&nbsp;
                                                <input type="radio" v-model="deny_advance_ordering" :value="true">  Deny &nbsp;&nbsp;
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-primary" @click="save">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
  import Vue from 'vue'
    import VeeValidate from 'vee-validate'
    import {EventBus} from '../../configs/EventBus'
    Vue.use(VeeValidate)
    export default {
        components:{},
        data(){
            return {
                deny_add_to_cart:'',
                deny_block:'',
                deny_advance_ordering:'',
                distributor:null,
              }
        },
        mounted(){
            let self = this
            $(self.$refs.modal).on('hidden.bs.modal', function (e) {
                 Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
        },            
        methods:{
            allowAll(){
                this.deny_advance_ordering = false
                this.deny_block = false
                this.deny_add_to_cart = false
            },
            denyAll(){
                this.deny_advance_ordering = true
                this.deny_block = true
                this.deny_add_to_cart = true
            },
            save(){
                let self = this
                let input = {
                    deny_add_to_cart : self.deny_add_to_cart,
                    deny_block : self.deny_block,
                    deny_advance_ordering : self.deny_advance_ordering,
                }
                if(self.distributor.company)
                {
                    let url = `companies/${self.distributor.company.id}/distributors/${self.distributor.id}/set_permissions`
                    self.$validator.validateAll()
                    .then(value => {
                        if(value)
                        {
                            EventBus.$emit('loader:show')
                            Vue.axios.put(url,input)
                            .then(response => {
                                EventBus.$emit('loader:hide')
                                swal({
                                    title:"Permissions set",
                                    icon:"success"
                                })
                            self.$emit('permissions-set',response.data)
                            $(self.$refs.modal).modal('hide')
                            })
                            .catch(error => {})
                        }
                    })
                }
            },
            show(distributor){
                let self = this
                self.distributor = Object.assign({},distributor)
                self.deny_add_to_cart = false
                self.deny_block = false
                self.deny_advance_ordering = false
                if(distributor.abilities.deny_add_to_cart)
                {
                    if(distributor.abilities.deny_add_to_cart == true) 
                        self.deny_add_to_cart = true
                    else
                        self.deny_add_to_cart = false
                }
                if(distributor.abilities.deny_block)
                {
                    if(distributor.abilities.deny_block == true) 
                        self.deny_block = true
                    else
                        self.deny_block = false
                }
                if(distributor.abilities.deny_advance_ordering)
                {
                    if(distributor.abilities.deny_advance_ordering == true) 
                        self.deny_advance_ordering = true
                    else
                        self.deny_advance_ordering = false
                }
                $(this.$refs.modal).modal('show')
            }
        },
    }
</script>

