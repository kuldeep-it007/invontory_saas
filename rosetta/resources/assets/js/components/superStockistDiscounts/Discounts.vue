<template>
	<div v-if="abilities.viewSSDiscounts">
		<section class="content-header">
			<h1>Discounts 
				<span class="pull-right"><button class="btn btn-sm btn-primary" @click="addDiscount" v-if="abilities.manageSSDiscounts">ADD</button></span>
			</h1>
		</section>
		<section class="content">
			<discounts ref="Discount"></discounts>
			<discount-modal
				ref="discountModal" 
				@discount-added="onDiscountAdded"
				:company="this.superStockist.company"
	            :superStockist="this.superStockist">
			</discount-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Discounts from './List'
	import DiscountModal from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			Discounts,
			DiscountModal,
		},
		methods:{
			addDiscount(){
				this.$refs.discountModal.show()
			},
			onDiscountAdded(discount){
        		this.$refs.Discount.reload()
            },
		}
	}
</script>