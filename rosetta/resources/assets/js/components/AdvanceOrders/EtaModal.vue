<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 v-if="item" class="modal-title"><span class="product-category">{{item.product.category.code}} &raquo; {{item.product.collection.code}}<template v-if="item.product.colour"> &raquo; {{item.product.colour.code}}</template></span></h4>
                </div>
                <div class="modal-body" v-if="item">
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Collection</th>
                                        <th>Colour</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{item.product.nav_id}}</td>
                                        <td>{{item.product.category.display_name}}</td>
                                        <td>{{item.product.collection.display_name}}</td>
                                        <td>
                                            <template v-if="item.product.colour">
                                                {{item.product.colour.display_name}}
                                            </template>
                                            <template v-else>
                                                &nbsp;
                                            </template>
                                        </td>
                                        <td>{{item.order_quantity}}<sup>{{item.product.base_unit_of_measure}}</sup></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>ETA</label>
                                    <date-picker v-model="eta" type="date" name="eta" format="DD MMM YYYY" lang="en" :not-before="new Date()"></date-picker>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-default btn-block" @click="set(item)">SET</button>
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
    import moment from 'moment'
    import DatePicker from 'vue2-datepicker'
    Vue.prototype.moment = moment
    Vue.use(VeeValidate)
    export default {
        mixins : [appMixin],
        components:{
            DatePicker,
        },
        props:['item', 'effectiveCompany', 'effectiveDistributor', 'effectiveSuperStockist', 'effectiveDealer'],
        data(){
            return {
                loaded:false,
                eta:null,
            }
        },
        filters: {
            moment: function (date) {
                return moment(date).format('MMMM Do YYYY, h:mm:ss a');
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
            moment: function () {
                return moment();
            },
            show(item){
                let self = this
                self.eta = item.eta ? moment.unix(item.eta).format('DD MMM YYYY') : null
                $(self.$refs.modal).modal('show')
            },
            set(item){
                let self = this
                let input = {
                    eta: self.eta ? moment(self.eta).format('X') : null
                }
                let url = ""
                if(self.effectiveDistributor)
                    url = `companies/${self.effectiveCompany.id}/distributors/${self.effectiveDistributor.id}/advance_items/${item.id}`
                if(self.effectiveSuperStockist)
                    url = `companies/${self.effectiveCompany.id}/super_stockists/${self.effectiveSuperStockist.id}/advance_items/${item.id}`
                else if(self.effectiveDealer)
                    url = `companies/${self.effectiveCompany.id}/dealers/${self.effectiveDealer.id}/advance_items/${item.id}`
                EventBus.$emit('loader:show')
                Vue.axios.put(url, input)
                .then(response => {
                    swal({
                        title:"ETA updated!",
                        icon:"success"
                    })
                    .then(value => {
                        self.$emit('eta-set',response.data)
                        $(self.$refs.modal).modal('hide')
                    }) 
                })
            },
        }
    }
</script>
<style scoped>
.product-category {
    display: block;
    color: #888;
    font-size: 14px;
}
</style>