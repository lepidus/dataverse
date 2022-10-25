<div id="draftDatasetFilesContainer">
	<list-panel :items="components.draftDatasetFilesList.items" style="margin-bottom: 1rem">
        <pkp-header slot="header">
            <h2>Datasets</h2>
			<spinner v-if="isLoading"></spinner>
            <template slot="actions">
                <pkp-button ref="datasetModalButton" @click="datasetFileModalOpen">
                    Add file
                </pkp-button>
            </template>
        </pkp-header>
			<template v-slot:item="item">
				<div class="listPanel__itemSummary">
					<div class="listPanel__itemIdentity">
						<div class="listPanel__itemTitle">
							{{ item.item.fileName }}
						</div>
					</div>
					<div class="listPanel__itemActions">
						<pkp-button @click="openDeleteModal(item.item.id)" class="pkpButton--isWarnable">
							{{ __('common.delete') }}
						</pkp-button>
					</div>
				</div>
			</template>
    </list-panel>
    <modal
		v-bind="MODAL_PROPS"
		name="datasetModal"
		@closed="datasetFileModalClose"
	>
		<modal-content
			close-label="common.close"
			modal-name="datasetModal"
			title="Dataset File Upload"
		>
            <pkp-form 
				v-bind="components.draftDatasetFileForm"
				@set="set"
				@success="formSuccess"
			>
			</pkp-form>
		</modal-content>
	</modal>

    <script type="text/javascript">
        pkp.registry.init('draftDatasetFilesContainer', 'DraftDatasetFilesPage', {$state|json_encode});
    </script>

</div>