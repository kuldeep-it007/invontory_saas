<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Add Product Attributes</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category Code</label>
                                <category-search 
                                    v-model="category" 
                                    :distributor-id="distributorId"
                                    :super-stockist-id="superStockistId"
                                    :dealer-id="dealerId"
                                    :company-id="companyId"
                                ></category-search>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category Display</label>
                                <input type="text" class="form-control" v-model="category_display" name="category_display">
                           </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Collection Code</label>
                                <collection-search v-model="collection" 
                                    :category="category"
                                    :distributor-id="this.$route.params.distributorid"
                                    :super-stockist-id="this.$route.params.superStockistId"
                                    :dealer-id="this.$route.params.dealerid"
                                    :company-id="this.$route.params.id"
                                ></collection-search>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Collection Display</label>
                                <input type="text" class="form-control" v-model="collection_display" name="collection_display">
                           </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Colour Code</label>
                                <colour-search v-model="colour" 
                                    :collection="collection"
                                    :distributor-id="this.$route.params.distributorid"
                                    :dealer-id="this.$route.params.dealerid"
                                    :company-id="this.$route.params.id"
                                ></colour-search>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Colour Display</label>
                                <input type="text" class="form-control" v-model="colour_display" name="colour_display">
                           </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-primary" :disabled="(!category || !collection)" @click="add">ADD</button>
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
    import CategorySearch from '../categories/Search'
    import CollectionSearch from '../collections/Search'
    import ColourSearch from '../colour/Search'
    Vue.use(VeeValidate)
    export default {
        mixins : [appMixin],
        components:{
            CategorySearch,
            CollectionSearch,
            ColourSearch,
        },
        props:['attribute', 'companyId', 'distributorId', 'dealerId', 'superStockistId'],
        data(){
            return {
                loaded             :false,
                category           : null,
                collection         : null,
                colour             : null,
                category_display   : null,
                collection_display : null,
                colour_display     : null,
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
                self.category = null    
                self.collection = null
                self.colour = null
                self.category_display = null    
                self.collection_display = null
                self.colour_display = null
            })
        },
        computed:{
        },
        methods:{
            show(){
                let self = this
                $(self.$refs.modal).modal('show')
            },
            add(){
                let self = this
                let input = {
                    distributorId : self.distributorId,
                    superStockistId : self.superStockistId,
                    dealerId : self.dealerId,
                    category_code : self.category.code,
                    collection_code : self.collection.code,
                    category_display : self.category_display,
                    collection_display : self.collection_display,
                }
                if(!self.category_display)
                    input.category_display = self.category.code
                if(!self.collection_display)
                    input.collection_display = self.collection.code
                if(self.colour)
                {
                    input.colour_code = self.colour.code
                    input.colour_display = self.colour_display
                    if(!self.colour_display)
                        input.colour_display = self.colour.code
                }
                let url = `companies/${self.companyId}/product_attributes`
                EventBus.$emit('loader:show')
                Vue.axios.post(url, input)
                .then(response => {
                    swal({
                        title:"Attribute added!",
                        icon:"success"
                    })
                    .then(value => {
                        self.$emit('attribute-added',response.data)
                     //   $(self.$refs.modal).modal('hide')
                     self.loaded = false
                        self.category = null    
                        self.collection = null
                        self.colour = null
                        self.category_display = null    
                        self.collection_display = null
                        self.colour_display = null
                    }) 
                })
                .catch(error => {
                if(error.response.status == 409)
                {
                    swal({
                            title: "Error",
                            text:error.response.data.error,
                            icon:'error'
                        })
                    .then(value => {
                        self.loaded = false
                        self.category = null    
                        self.collection = null
                        self.colour = null
                        self.category_display = null    
                        self.collection_display = null
                        self.colour_display = null
                      //  $(self.$refs.modal).modal('hide')
                    })
                }
            })
            },
        }
    }
</script>