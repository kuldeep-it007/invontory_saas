<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 v-if="attribute" class="modal-title"><span>{{attribute.category_code}} &raquo; {{attribute.collection_code}}<template v-if="attribute.colour_code"> &raquo; {{attribute.colour_code}}</template></span></h4>
                </div>
                <div class="modal-body" v-if="attribute">
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category Display</th>
                                        <th>Collection Display</th>
                                        <th v-if="attribute.colour_code != null">Colour Display</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div>
                                                <template v-if="attribute.colour_code == null">
                                                    <input type="text" class="form-control input-sm"  v-model="attribute.category_display"></input>
                                                </template>
                                                <template v-if="attribute.colour_code != null">
                                                    {{attribute.category_display}}
                                                </template>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <template v-if="attribute.colour_code == null">
                                                    <input type="text" class="form-control input-sm"  v-model="attribute.collection_display"></input>
                                                </template>
                                                <template v-if="attribute.colour_code != null">
                                                    {{attribute.collection_display}}
                                                </template>
                                            </div>
                                        </td>
                                        <td v-if="attribute.colour_code != null"><input type="text" class="form-control input-sm"  v-model="attribute.colour_display"></input></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="col-md-2">
                                <button class="btn btn-default btn-block" @click="update(attribute)">Update</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import Vue from 'vue'
    import appMixin from '../../vuex-mixin.js'
    import {EventBus} from '../../configs/EventBus'
    import VeeValidate from 'vee-validate'
    Vue.use(VeeValidate)
    export default {
        mixins : [appMixin],
        components:{
        },
        props:['attribute', 'companyId', 'distributorId', 'dealerId', 'superStokistId'],
        data(){
            return {
                loaded:false,
            }
        },
        mounted(){
            let self = this
            $(this.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.loaded = false
            })
        },
        computed:{
        },
        methods:{
            show(attribute){
                let self = this
                $(self.$refs.modal).modal('show')
            },
            update(attribute){
                let self = this
                let $refresh = null
                let input = {}
                input.distributorId = self.distributorId
                input.dealerId = self.dealerId
                if(!self.attribute.colour_code)
                {
                /*    swal({
                        title: 'If you want to go ahead with update, then Category/Collection Display Name will be updated for all corresponding Products for this Customer. Press Cancel if you wish to discard the update.',
                        icon:'warning',
                        buttons: {
                            cancel: true,
                            confirm: true,
                        },
                    })
                    .then(value=>{
                        if(!value)
                            return
                    })*/
                    input.category_display = self.attribute.category_display
                    input.collection_display = self.attribute.collection_display
                    $refresh = true
                }
                if(self.attribute.colour_code)
                {
                    input.colour_display = self.attribute.colour_display
                }

                let url = `companies/${self.companyId}/product_attributes/${attribute.id}`
                EventBus.$emit('loader:show')
                Vue.axios.put(url, input)
                .then(response => {
                    swal({
                        title:"Attribute updated!",
                        icon:"success"
                    })
                    .then(value => {
                        self.$emit('attribute-update',response.data, $refresh)
                        $(self.$refs.modal).modal('hide')
                    }) 
                })
            },
        }
    }
</script>