<script setup>
import { useHelpers } from "kirbyuse"
import { field } from "kirbyuse/props"
import { computed } from "vue"

const props = defineProps({
	...field,
	items: {
		type: Array,
		default: () => []
	}
})

const helpers = useHelpers()

const rows = computed(() =>
	props.items.map((item) => ({
		...item,
		buttons: item.status ? [helpers.page.status(item.status, true, item.flag)] : []
	}))
)
</script>

<template>
	<k-field
		:id="id"
		:help="help"
		:input="false"
		:label="label"
		:name="name"
		:type="type"
		class="k-file-usage-field"
	>
		<k-items v-if="items.length" :items="rows" layout="list" />
		<k-empty v-else icon="file-image" layout="list">
			{{ $t("file-usage.empty") }}
		</k-empty>
	</k-field>
</template>
