<template>
    <div>
        <text-input
            :type="inputType"
            :value="formattedValue"
            :prepend="symbol"
            :isReadOnly="config.read_only || readOnly"
            placeholder="00.00"
            @input="update" />
    </div>
</template>

<script>
    export default {
        name: 'money-fieldtype',

        mixins: [Fieldtype],

        props: ['meta'],

        data() {
            return {
                symbol: this.meta.symbol,
                formattedValue: this.value,
            }
        },

        computed: {
            inputType() {
                return this.show
            },
        },

        mounted() {
            if (isNaN(parseFloat(this.value)) == false) {
                this.formattedValue = parseFloat(this.value).toFixed(2)
            }
        },
    };
</script>
