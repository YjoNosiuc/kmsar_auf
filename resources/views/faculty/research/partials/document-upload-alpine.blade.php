<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('kmsarDocumentUpload', (uploadedFileCount = 0, maxFiles = 10) => ({
        uploadType: 'file',
        uploadedFileCount: Number(uploadedFileCount) || 0,
        maxFiles: Number(maxFiles) || 10,
        selectedFiles: [],
        activePreview: null,
        nextFileUid: 1,
        slotLimitMessage: @json(__('You can only upload :count more file(s) for this research.')),

        get selectedCount() {
            return this.selectedFiles.length;
        },

        get remainingSlots() {
            return Math.max(0, this.maxFiles - this.uploadedFileCount);
        },

        get remainingAfterSelection() {
            return Math.max(0, this.remainingSlots - this.selectedCount);
        },

        get counterText() {
            const segments = [
                `${this.uploadedFileCount} ${@json(__('of'))} ${this.maxFiles} ${@json(__('files uploaded'))}`,
            ];

            if (this.selectedCount > 0) {
                segments.push(`${this.selectedCount} ${@json(__('selected'))}`);
            }

            segments.push(`${this.remainingAfterSelection} ${@json(__('remaining'))}`);

            return segments.join(' · ');
        },

        formatFileSize(bytes) {
            if (! bytes && bytes !== 0) {
                return '—';
            }
            if (bytes < 1024) {
                return `${bytes} B`;
            }
            if (bytes < 1048576) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }

            return `${(bytes / 1048576).toFixed(1)} MB`;
        },

        previewKind(file) {
            const name = (file.name || '').toLowerCase();
            const mime = (file.type || '').toLowerCase();

            if (mime === 'application/pdf' || name.endsWith('.pdf')) {
                return 'pdf';
            }
            if (mime.startsWith('image/') || /\.(jpe?g|png|gif|webp|bmp)$/i.test(name)) {
                return 'image';
            }

            return 'other';
        },

        buildFileEntry(file) {
            const kind = this.previewKind(file);
            const canPreview = kind === 'pdf' || kind === 'image';
            const previewUrl = canPreview ? URL.createObjectURL(file) : null;

            return {
                uid: this.nextFileUid++,
                file,
                name: file.name,
                sizeLabel: this.formatFileSize(file.size),
                kind,
                canPreview,
                previewUrl,
            };
        },

        revokePreviewUrls() {
            this.selectedFiles.forEach((item) => {
                if (item.previewUrl) {
                    try {
                        URL.revokeObjectURL(item.previewUrl);
                    } catch (e) {}
                }
            });
        },

        setActivePreview(item) {
            this.activePreview = item?.canPreview ? item : null;
        },

        autoPreviewFirst() {
            const firstPreviewable = this.selectedFiles.find((item) => item.canPreview);
            this.setActivePreview(firstPreviewable || null);
        },

        clearSelected() {
            this.revokePreviewUrls();
            this.selectedFiles = [];
            this.activePreview = null;
            const input = this.$refs.fileInput;
            if (input) {
                input.value = '';
            }
        },

        syncInput() {
            const input = this.$refs.fileInput;
            if (! input) {
                return;
            }
            const dt = new DataTransfer();
            this.selectedFiles.forEach((item) => dt.items.add(item.file));
            input.files = dt.files;
        },

        addFiles(fileList) {
            const files = Array.from(fileList || []).filter(Boolean);
            if (files.length === 0) {
                return;
            }

            if (files.length > this.remainingSlots) {
                alert(this.slotLimitMessage.replace(':count', String(this.remainingSlots)));
                this.clearSelected();

                return;
            }

            this.revokePreviewUrls();
            this.selectedFiles = files.map((file) => this.buildFileEntry(file));
            this.syncInput();
            this.autoPreviewFirst();
        },

        handleFileSelect(event) {
            this.addFiles(event.target.files);
        },

        handleDrop(event) {
            event.preventDefault();
            event.stopPropagation();

            const zone = event.currentTarget;
            if (zone) {
                zone.classList.remove('kmsar-dropzone--drag');
            }

            if (this.remainingSlots === 0) {
                return;
            }

            const files = event.dataTransfer?.files;
            if (! files || files.length === 0) {
                return;
            }

            this.addFiles(files);
        },

        removeFile(uid) {
            const index = this.selectedFiles.findIndex((item) => item.uid === uid);
            if (index === -1) {
                return;
            }

            const [removed] = this.selectedFiles.splice(index, 1);
            if (removed?.previewUrl) {
                try {
                    URL.revokeObjectURL(removed.previewUrl);
                } catch (e) {}
            }

            if (this.activePreview?.uid === uid) {
                this.autoPreviewFirst();
            }

            this.syncInput();
        },

        previewFile(item) {
            if (! item?.canPreview) {
                return;
            }

            this.setActivePreview(item);
        },
    }));

    Alpine.data('kmsarResearchDocumentsPage', (uploadedFileCount = 0, maxFiles = 10, documentCount = 0) => ({
        tab: 'upload',
        documentCount: Number(documentCount) || 0,
        uploadType: 'file',
        uploadedFileCount: Number(uploadedFileCount) || 0,
        maxFiles: Number(maxFiles) || 10,
        selectedFiles: [],
        activePreview: null,
        nextFileUid: 1,
        slotLimitMessage: @json(__('You can only upload :count more file(s) for this research.')),

        get selectedCount() {
            return this.selectedFiles.length;
        },

        get remainingSlots() {
            return Math.max(0, this.maxFiles - this.uploadedFileCount);
        },

        get remainingAfterSelection() {
            return Math.max(0, this.remainingSlots - this.selectedCount);
        },

        get counterText() {
            const segments = [
                `${this.uploadedFileCount} ${@json(__('of'))} ${this.maxFiles} ${@json(__('files uploaded'))}`,
            ];

            if (this.selectedCount > 0) {
                segments.push(`${this.selectedCount} ${@json(__('selected'))}`);
            }

            segments.push(`${this.remainingAfterSelection} ${@json(__('remaining'))}`);

            return segments.join(' · ');
        },

        formatFileSize(bytes) {
            if (! bytes && bytes !== 0) {
                return '—';
            }
            if (bytes < 1024) {
                return `${bytes} B`;
            }
            if (bytes < 1048576) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }

            return `${(bytes / 1048576).toFixed(1)} MB`;
        },

        previewKind(file) {
            const name = (file.name || '').toLowerCase();
            const mime = (file.type || '').toLowerCase();

            if (mime === 'application/pdf' || name.endsWith('.pdf')) {
                return 'pdf';
            }
            if (mime.startsWith('image/') || /\.(jpe?g|png|gif|webp|bmp)$/i.test(name)) {
                return 'image';
            }

            return 'other';
        },

        buildFileEntry(file) {
            const kind = this.previewKind(file);
            const canPreview = kind === 'pdf' || kind === 'image';
            const previewUrl = canPreview ? URL.createObjectURL(file) : null;

            return {
                uid: this.nextFileUid++,
                file,
                name: file.name,
                sizeLabel: this.formatFileSize(file.size),
                kind,
                canPreview,
                previewUrl,
            };
        },

        revokePreviewUrls() {
            this.selectedFiles.forEach((item) => {
                if (item.previewUrl) {
                    try {
                        URL.revokeObjectURL(item.previewUrl);
                    } catch (e) {}
                }
            });
        },

        setActivePreview(item) {
            this.activePreview = item?.canPreview ? item : null;
        },

        autoPreviewFirst() {
            const firstPreviewable = this.selectedFiles.find((item) => item.canPreview);
            this.setActivePreview(firstPreviewable || null);
        },

        clearSelected() {
            this.revokePreviewUrls();
            this.selectedFiles = [];
            this.activePreview = null;
            const input = this.$refs.fileInput;
            if (input) {
                input.value = '';
            }
        },

        syncInput() {
            const input = this.$refs.fileInput;
            if (! input) {
                return;
            }
            const dt = new DataTransfer();
            this.selectedFiles.forEach((item) => dt.items.add(item.file));
            input.files = dt.files;
        },

        addFiles(fileList) {
            const files = Array.from(fileList || []).filter(Boolean);
            if (files.length === 0) {
                return;
            }

            if (files.length > this.remainingSlots) {
                alert(this.slotLimitMessage.replace(':count', String(this.remainingSlots)));
                this.clearSelected();

                return;
            }

            this.revokePreviewUrls();
            this.selectedFiles = files.map((file) => this.buildFileEntry(file));
            this.syncInput();
            this.autoPreviewFirst();
        },

        handleFileSelect(event) {
            this.addFiles(event.target.files);
        },

        handleDrop(event) {
            event.preventDefault();
            event.stopPropagation();

            const zone = event.currentTarget;
            if (zone) {
                zone.classList.remove('kmsar-dropzone--drag');
            }

            if (this.remainingSlots === 0) {
                return;
            }

            const files = event.dataTransfer?.files;
            if (! files || files.length === 0) {
                return;
            }

            this.addFiles(files);
        },

        removeFile(uid) {
            const index = this.selectedFiles.findIndex((item) => item.uid === uid);
            if (index === -1) {
                return;
            }

            const [removed] = this.selectedFiles.splice(index, 1);
            if (removed?.previewUrl) {
                try {
                    URL.revokeObjectURL(removed.previewUrl);
                } catch (e) {}
            }

            if (this.activePreview?.uid === uid) {
                this.autoPreviewFirst();
            }

            this.syncInput();
        },

        previewFile(item) {
            if (! item?.canPreview) {
                return;
            }

            this.setActivePreview(item);
        },
    }));
});
</script>
