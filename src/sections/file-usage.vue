<script setup>
import { computed, ref, useHelpers, useSection } from "kirbyuse"
import { section } from "kirbyuse/props"

const props = defineProps(section)

const helpers = useHelpers()
const { load } = useSection()

const label = ref(null)
const items = ref(null)

const rows = computed(() =>
	items.value.map((item) => ({
		...item,
		buttons: item.status ? [helpers.page.status(item.status, true)] : []
	}))
)

load({ parent: props.parent, name: props.name }).then((response) => {
	label.value = response.label
	items.value = response.items
})
</script>

<template>
	<k-section v-if="items" :label="label" class="k-file-usage-section">
		<k-items v-if="items.length" :items="rows" layout="list" />
		<k-empty v-else icon="file-image" layout="list">
			{{ $t("file-usage.empty") }}
		</k-empty>
	</k-section>
</template>
