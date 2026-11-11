<template>
    <div v-if="abilities.viewOnlineDiscounts">
		<section class="content-header">
			<h1>Online Discounts <span class="pull-right"><button class="btn btn-sm btn-primary" @click="add" v-if="abilities.addOnlineDiscount && company">ADD</button></span></h1>
		</section>
		<section class="content">
            <div class="box box-widget" v-if="!state.user.company">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Region</label>
                                <region v-model="region"></region>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Company</label>
                                <company v-model="company" :region="region"></company>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <discounts :company="company" v-if="company" @online-discount:edit="editDiscount" ref="discountList"></discounts>
            <discount-modal 
                :company="company" 
                v-if="company" 
                ref="discountModal"
                @online-discount:updated="discountUpdated"
            ></discount-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
import appMixins from '../../vuex-mixin'
import Region from '../region/Select'
import Company from '../companies/Select'
import Discounts from './List'
import DiscountModal from './Modal'
export default {
    mixins:[appMixins],
    components:{
        Region,
        Company,
        Discounts,
        DiscountModal,
    },
    data(){
        return {
            company:null,
            region:null,
        }
    },
    mounted(){
        if(this.state.user.company)
        {
            this.company = this.state.user.company
        }
    },
    methods:{
        discountUpdated(data){
            this.$refs.discountList.updateDiscount(data.discount,data.index)
        },
        editDiscount(data){
            this.$refs.discountModal.show(data.discount,data.index)
        },
        add(){
            this.$refs.discountModal.show()
        }
    }
}
</script>