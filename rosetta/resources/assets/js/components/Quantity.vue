<template>
	<div class="input-group">
		<div class="input-group-btn">
			<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-html="btnHtml"></button>
			<ul class="dropdown-menu">
				<li><a href="#" @click.prevent="selectOperator('=')">Equals</i></a></li>
				<li><a href="#" @click.prevent="selectOperator('<')">Less than</i></a></li>
				<li><a href="#" @click.prevent="selectOperator('>')">More than</i></a></li>
			</ul>
		</div>
		<input type="text" class="form-control" v-model.number="quantity">
    </div>
</template>
<script>
	export default{
		props:{
			value:{
				type:String,
				required:false
			}
		},
		data(){
			return {
				quantity:1,
				operator:'>'
			}
		},
		computed:{
			btnHtml(){
				if(this.operator == '=')
					return 'Equals'
				if(this.operator == '<')
					return 'Less than'
				if(this.operator == '>')
					return 'More than'
			}
		},
		methods:{
			selectOperator(operator){
				this.operator = operator
				if(this.quantity)
					this.$emit('input',`${this.operator}${this.quantity}`)
				else
					this.$emit('input',null)
			}
		},
		mounted(){
			if(this.value)
			{

			}
			else
			{
				this.operator = '>'
				this.quantity = ""
			}
		},
		watch:{
			quantity(){
				if(this.quantity)
				{
					this.$emit('input',`${this.operator}${this.quantity}`)
				}
				else
				{
					this.$emit('input',null)	
				}
			}
		}
	}
</script>