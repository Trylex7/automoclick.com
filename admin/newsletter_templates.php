<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur d'Email Drag & Drop - Style Brevo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brevo: {
                            blue: '#0066CC',
                            'blue-light': '#E6F2FF',
                            'blue-dark': '#004C99',
                            gray: '#F8F9FA',
                            'gray-dark': '#6C757D'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 h-screen flex">
    <!-- Sidebar - Palette d'éléments -->
    <div class="w-80 bg-white border-r border-gray-300 overflow-y-auto shadow-sm">
        <!-- Header de la sidebar -->
        <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-brevo-blue to-brevo-blue-dark">
            <h2 class="text-white font-semibold text-lg flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                Éléments
            </h2>
        </div>
        
        <!-- Palette d'éléments -->
        <div class="p-4">
            <div class="space-y-2" id="elementPalette">
                <div class="draggable-element group flex items-center gap-3 p-3 bg-white border-2 border-gray-200 rounded-lg cursor-grab hover:border-brevo-blue hover:bg-brevo-blue-light transition-all duration-200 hover:shadow-md" 
                     draggable="true" data-type="text">
                    <div class="w-10 h-10 bg-gray-100 group-hover:bg-white rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-brevo-blue" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5 4v3h5.5v12h3V7H19V4z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Texte</div>
                        <div class="text-xs text-gray-500">Ajoutez du contenu textuel</div>
                    </div>
                </div>
                
                <div class="draggable-element group flex items-center gap-3 p-3 bg-white border-2 border-gray-200 rounded-lg cursor-grab hover:border-brevo-blue hover:bg-brevo-blue-light transition-all duration-200 hover:shadow-md" 
                     draggable="true" data-type="title">
                    <div class="w-10 h-10 bg-gray-100 group-hover:bg-white rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-brevo-blue" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5 4v3h5.5v12h3V7H19V4z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Titre</div>
                        <div class="text-xs text-gray-500">Titre principal ou section</div>
                    </div>
                </div>
                
                <div class="draggable-element group flex items-center gap-3 p-3 bg-white border-2 border-gray-200 rounded-lg cursor-grab hover:border-brevo-blue hover:bg-brevo-blue-light transition-all duration-200 hover:shadow-md" 
                     draggable="true" data-type="image">
                    <div class="w-10 h-10 bg-gray-100 group-hover:bg-white rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-brevo-blue" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Image</div>
                        <div class="text-xs text-gray-500">Ajoutez des visuels</div>
                    </div>
                </div>
                
                <div class="draggable-element group flex items-center gap-3 p-3 bg-white border-2 border-gray-200 rounded-lg cursor-grab hover:border-brevo-blue hover:bg-brevo-blue-light transition-all duration-200 hover:shadow-md" 
                     draggable="true" data-type="button">
                    <div class="w-10 h-10 bg-gray-100 group-hover:bg-white rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-brevo-blue" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="6" width="18" height="12" rx="2"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Bouton</div>
                        <div class="text-xs text-gray-500">Call-to-action</div>
                    </div>
                </div>
                
                <div class="draggable-element group flex items-center gap-3 p-3 bg-white border-2 border-gray-200 rounded-lg cursor-grab hover:border-brevo-blue hover:bg-brevo-blue-light transition-all duration-200 hover:shadow-md" 
                     draggable="true" data-type="divider">
                    <div class="w-10 h-10 bg-gray-100 group-hover:bg-white rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-brevo-blue" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 12h18"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Séparateur</div>
                        <div class="text-xs text-gray-500">Ligne de séparation</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone de propriétés -->
        <div class="border-t border-gray-200 hidden" id="propertiesPanel">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <div class="w-2 h-2 bg-brevo-blue rounded-full"></div>
                    Propriétés de l'élément
                </h3>
            </div>
            <div id="propertiesContent" class="p-4 space-y-4 bg-white"></div>
        </div>
    </div>

    <!-- Zone principale -->
    <div class="flex-1 flex flex-col">
        <!-- Barre d'outils style Brevo -->
        <div class="bg-white border-b border-gray-300 px-6 py-3 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-gray-800">Éditeur d'Email</h1>
                <div class="text-sm text-gray-500">Créez votre email comme un pro</div>
            </div>
            <div class="flex gap-3">
                <button class="toolbar-btn preview flex items-center gap-2 px-4 py-2 bg-brevo-blue-light text-brevo-blue border border-brevo-blue rounded-lg hover:bg-brevo-blue hover:text-white transition-all font-medium text-sm" 
                        id="previewBtn">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                    </svg>
                    Aperçu
                </button>
                <button class="toolbar-btn code flex items-center gap-2 px-4 py-2 bg-green-100 text-green-700 border border-green-300 rounded-lg hover:bg-green-200 transition-all font-medium text-sm" 
                        id="codeBtn">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/>
                    </svg>
                    HTML
                </button>
                <button class="toolbar-btn download flex items-center gap-2 px-4 py-2 bg-brevo-blue text-white rounded-lg hover:bg-brevo-blue-dark transition-all font-medium text-sm shadow-md" 
                        id="downloadBtn">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                    </svg>
                    Télécharger
                </button>
            </div>
        </div>

        <!-- Zone de construction -->
        <div class="flex-1 p-8 overflow-y-auto bg-gray-100">
            <div class="max-w-3xl mx-auto">
                <!-- Canvas de l'email -->
                <div class="bg-white rounded-xl shadow-xl border border-gray-300 transition-all duration-300" 
                     id="emailCanvas">
                    <!-- Contrôles du canvas -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 border-b border-gray-200 rounded-t-xl">
                        <div class="flex items-center gap-3">
                            <div class="flex gap-1">
                                <div class="w-3 h-3 bg-red-400 rounded-full"></div>
                                <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
                                <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                            </div>
                            <span class="text-sm text-gray-600 font-medium">Votre Email</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span>600px de largeur maximale</span>
                        </div>
                    </div>
                    
                    <!-- Contenu de l'email -->
                    <div class="p-8 min-h-96 bg-white" id="emailContent">
                        <div class="text-center text-gray-400 py-20" id="emptyState">
                            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-600 mb-2">Commencez à créer votre email</h3>
                            <p class="text-sm text-gray-500 mb-6">Glissez-déposez des éléments depuis la palette de gauche</p>
                            <div class="flex justify-center">
                                <div class="animate-bounce">
                                    <svg class="w-6 h-6 text-brevo-blue" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M7 14l5-5 5 5z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Viewer de code -->
            <div class="max-w-3xl mx-auto bg-gray-900 rounded-xl shadow-xl border border-gray-700 hidden" 
                 id="codeViewer">
                <div class="p-4 bg-gray-800 border-b border-gray-700 rounded-t-xl">
                    <div class="flex items-center gap-3">
                        <div class="flex gap-1">
                            <div class="w-3 h-3 bg-red-400 rounded-full"></div>
                            <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
                            <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                        </div>
                        <span class="text-sm text-gray-300 font-medium">Code HTML</span>
                    </div>
                </div>
                <pre class="p-6 font-mono text-sm text-green-400 overflow-auto max-h-96 whitespace-pre-wrap leading-relaxed" 
                     id="codeContent"></pre>
            </div>
        </div>
    </div>

    <!-- Modal d'upload d'image -->
    <div class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" id="imageModal">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Ajouter une image</h3>
                    <button class="text-gray-400 hover:text-gray-600" onclick="closeImageModal()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <!-- Upload de fichier -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Télécharger depuis votre ordinateur</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-brevo-blue transition-colors">
                            <input type="file" id="imageUpload" class="hidden" accept="image/*" onchange="handleImageUpload(event)">
                            <label for="imageUpload" class="cursor-pointer">
                                <div class="w-12 h-12 bg-brevo-blue-light rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-brevo-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold text-brevo-blue">Cliquez pour parcourir</span> ou glissez votre image ici
                                </div>
                                <div class="text-xs text-gray-400 mt-1">PNG, JPG, GIF jusqu'à 5MB</div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- URL d'image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ou entrez une URL d'image</label>
                        <input type="url" id="imageUrl" placeholder="https://exemple.com/image.jpg" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brevo-blue focus:border-transparent">
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button onclick="closeImageModal()" 
                            class="flex-1 px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                        Annuler
                    </button>
                    <button onclick="confirmImageSelection()" 
                            class="flex-1 px-4 py-2 bg-brevo-blue text-white rounded-lg hover:bg-brevo-blue-dark transition-colors">
                        Ajouter l'image
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        class EmailEditor {
            constructor() {
                this.elements = [];
                this.selectedElement = null;
                this.draggedType = null;
                this.showPreview = false;
                this.showCode = false;
                this.elementCounter = 0;
                this.currentImageElement = null;
                
                this.initEventListeners();
                this.loadElementTypes();
            }

            loadElementTypes() {
                this.elementTypes = {
                    text: {
                        defaultProps: {
                            content: 'Votre texte personnalisé ici. Vous pouvez modifier ce contenu à tout moment.',
                            fontSize: '16px',
                            color: '#374151',
                            textAlign: 'left',
                            fontWeight: 'normal',
                            lineHeight: '1.6'
                        }
                    },
                    title: {
                        defaultProps: {
                            content: 'Titre Accrocheur',
                            fontSize: '28px',
                            color: '#1f2937',
                            textAlign: 'center',
                            fontWeight: 'bold',
                            lineHeight: '1.3'
                        }
                    },
                    image: {
                        defaultProps: {
                            src: 'https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            alt: 'Image descriptive',
                            width: '100%',
                            borderRadius: '12px'
                        }
                    },
                    button: {
                        defaultProps: {
                            text: 'Découvrir maintenant',
                            backgroundColor: '#0066CC',
                            color: '#ffffff',
                            borderRadius: '8px',
                            padding: '14px 28px',
                            textAlign: 'center',
                            fontSize: '16px',
                            fontWeight: '600'
                        }
                    },
                    divider: {
                        defaultProps: {
                            height: '2px',
                            backgroundColor: '#e5e7eb',
                            margin: '24px 0',
                            borderRadius: '1px'
                        }
                    }
                };
            }

            initEventListeners() {
                // Drag and drop
                const draggableElements = document.querySelectorAll('.draggable-element');
                draggableElements.forEach(element => {
                    element.addEventListener('dragstart', (e) => {
                        this.draggedType = e.currentTarget.dataset.type;
                        e.dataTransfer.effectAllowed = 'copy';
                        e.currentTarget.style.opacity = '0.6';
                        e.currentTarget.style.transform = 'scale(0.95)';
                    });

                    element.addEventListener('dragend', (e) => {
                        e.currentTarget.style.opacity = '1';
                        e.currentTarget.style.transform = 'scale(1)';
                    });
                });

                // Zone de drop
                const canvas = document.getElementById('emailContent');
                
                canvas.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'copy';
                });

                canvas.addEventListener('dragenter', (e) => {
                    e.preventDefault();
                    canvas.classList.add('bg-blue-50', 'ring-2', 'ring-brevo-blue', 'ring-opacity-50');
                });

                canvas.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    if (!canvas.contains(e.relatedTarget)) {
                        canvas.classList.remove('bg-blue-50', 'ring-2', 'ring-brevo-blue', 'ring-opacity-50');
                    }
                });

                canvas.addEventListener('drop', (e) => {
                    e.preventDefault();
                    canvas.classList.remove('bg-blue-50', 'ring-2', 'ring-brevo-blue', 'ring-opacity-50');
                    
                    if (this.draggedType) {
                        if (this.draggedType === 'image') {
                            this.openImageModal();
                        } else {
                            this.addElement(this.draggedType);
                        }
                        this.draggedType = null;
                    }
                });

                // Boutons toolbar
                document.getElementById('previewBtn').addEventListener('click', () => {
                    this.togglePreview();
                });

                document.getElementById('codeBtn').addEventListener('click', () => {
                    this.toggleCode();
                });

                document.getElementById('downloadBtn').addEventListener('click', () => {
                    this.downloadHTML();
                });

                // Clic sur le canvas pour désélectionner
                canvas.addEventListener('click', (e) => {
                    if (e.target === canvas || e.target.closest('#emptyState')) {
                        this.selectElement(null);
                    }
                });
            }

            openImageModal() {
                document.getElementById('imageModal').classList.remove('hidden');
            }

            addElement(type, customProps = {}) {
                if (!type || !this.elementTypes[type]) return;

                const element = {
                    id: `element_${++this.elementCounter}`,
                    type: type,
                    props: { ...this.elementTypes[type].defaultProps, ...customProps }
                };

                this.elements.push(element);
                this.renderCanvas();
                this.selectElement(element);
            }

            selectElement(element) {
                this.selectedElement = element;
                this.updateSelection();
                this.renderProperties();
            }

            updateSelection() {
                document.querySelectorAll('.canvas-element').forEach(el => {
                    el.classList.remove('ring-2', 'ring-brevo-blue', 'shadow-lg');
                });

                if (this.selectedElement) {
                    const elementDiv = document.querySelector(`[data-element-id="${this.selectedElement.id}"]`);
                    if (elementDiv) {
                        elementDiv.classList.add('ring-2', 'ring-brevo-blue', 'shadow-lg');
                    }
                }
            }

            updateElement(id, newProps) {
                const element = this.elements.find(el => el.id === id);
                if (element) {
                    element.props = { ...element.props, ...newProps };
                    this.renderCanvas();
                    if (this.selectedElement && this.selectedElement.id === id) {
                        this.selectedElement = element;
                    }
                }
            }

            deleteElement(id) {
                this.elements = this.elements.filter(el => el.id !== id);
                this.selectElement(null);
                this.renderCanvas();
            }

            renderCanvas() {
                const canvas = document.getElementById('emailContent');
                const emptyState = document.getElementById('emptyState');

                if (this.elements.length === 0) {
                    emptyState.classList.remove('hidden');
                    return;
                }

                emptyState.classList.add('hidden');
                
                // Supprimer les éléments existants
                const existingElements = canvas.querySelectorAll('.canvas-element');
                existingElements.forEach(el => el.remove());

                this.elements.forEach(element => {
                    const elementDiv = document.createElement('div');
                    elementDiv.className = 'canvas-element mb-4 cursor-pointer transition-all duration-200 hover:shadow-sm rounded-lg p-3 relative group';
                    elementDiv.dataset.elementId = element.id;
                    
                    elementDiv.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.selectElement(element);
                    });

                    // Icône d'édition au hover
                    const editIcon = document.createElement('div');
                    editIcon.className = 'absolute top-1 right-1 w-6 h-6 bg-brevo-blue text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs';
                    editIcon.innerHTML = '✎';
                    elementDiv.appendChild(editIcon);

                    const contentDiv = document.createElement('div');
                    contentDiv.innerHTML = this.renderElement(element);
                    elementDiv.appendChild(contentDiv);
                    
                    canvas.appendChild(elementDiv);
                });
            }

            renderElement(element) {
                switch (element.type) {
                    case 'text':
                    case 'title':
                        return `<div style="
                            font-size: ${element.props.fontSize};
                            color: ${element.props.color};
                            text-align: ${element.props.textAlign};
                            font-weight: ${element.props.fontWeight};
                            line-height: ${element.props.lineHeight || '1.5'};
                        ">${element.props.content}</div>`;

                    case 'image':
                        return `<img src="${element.props.src}" 
                                    alt="${element.props.alt}"
                                    style="width: ${element.props.width}; height: auto; border-radius: ${element.props.borderRadius}; display: block; margin: 0 auto;">`;

                    case 'button':
                        return `<div style="text-align: ${element.props.textAlign};">
                                    <a href="#" style="
                                        display: inline-block;
                                        background-color: ${element.props.backgroundColor};
                                        color: ${element.props.color};
                                        border-radius: ${element.props.borderRadius};
                                        padding: ${element.props.padding};
                                        text-decoration: none;
                                        font-size: ${element.props.fontSize};
                                        font-weight: ${element.props.fontWeight};
                                        transition: transform 0.2s ease;
                                    " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">${element.props.text}</a>
                                </div>`;

                    case 'divider':
                        return `<div style="
                            height: ${element.props.height};
                            background-color: ${element.props.backgroundColor};
                            margin: ${element.props.margin};
                            border-radius: ${element.props.borderRadius};
                        "></div>`;

                    default:
                        return '';
                }
            }

            renderProperties() {
                const panel = document.getElementById('propertiesPanel');
                const content = document.getElementById('propertiesContent');

                if (!this.selectedElement) {
                    panel.classList.add('hidden');
                    return;
                }

                panel.classList.remove('hidden');
                content.innerHTML = this.generatePropertiesForm(this.selectedElement);
            }

            generatePropertiesForm(element) {
                let html = '';

                switch (element.type) {
                    case 'text':
                    case 'title':
                        html = `
                            <div class="bg-gray-50 p-3 rounded-lg mb-4">
                                <div class="text-xs text-gray-600 mb-2 uppercase tracking-wide">Contenu</div>
                                <textarea class="w-full p-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brevo-blue focus:border-transparent resize-none" 
                                         rows="4" onchange="editor.updateElement('${element.id}', {content: this.value})" placeholder="Votre contenu ici...">${element.props.content}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Taille</label>
                                    <select class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brevo-blue focus:border-transparent bg-white" 
                                           onchange="editor.updateElement('${element.id}', {fontSize: this.value})">
                                        <option value="12px" ${element.props.fontSize === '12px' ? 'selected' : ''}>12px - Petit</option>
                                        <option value="14px" ${element.props.fontSize === '14px' ? 'selected' : ''}>14px - Normal</option>
                                        <option value="16px" ${element.props.fontSize === '16px' ? 'selected' : ''}>16px - Moyen</option>
                                        <option value="18px" ${element.props.fontSize === '18px' ? 'selected' : ''}>18px - Grand</option>
                                        <option value="20px" ${element.props.fontSize === '20px' ? 'selected' : ''}>20px - Large</option>
                                        <option value="24px" ${element.props.fontSize === '24px' ? 'selected' : ''}>24px - Titre</option>
                                        <option value="28px" ${element.props.fontSize === '28px' ? 'selected' : ''}>28px - Gros titre</option>
                                        <option value="32px" ${element.props.fontSize === '32px' ? 'selected' : ''}>32px - Très gros</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Couleur</label>
                                    <input type="color" class="w-full h-10 border border-gray-300 rounded-lg cursor-pointer" 
                                          value="${element.props.color}" onchange="editor.updateElement('${element.id}', {color: this.value})">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Alignement</label>
                                <div class="flex gap-1 bg-gray-100 p-1 rounded-lg">
                                    <button class="flex-1 p-2 rounded-md text-sm transition-all ${element.props.textAlign === 'left' ? 'bg-brevo-blue text-white shadow' : 'text-gray-600 hover:bg-gray-200'}" 
                                           onclick="editor.updateElement('${element.id}', {textAlign: 'left'})">
                                        <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M3 21h12v-2H3v2zm0-4h18v-2H3v2zm0-4h18v-2H3v2zm0-4h12v-2H3v2zm0-4h18v-2H3v2z"/>
                                        </svg>
                                    </button>
                                    <button class="flex-1 p-2 rounded-md text-sm transition-all ${element.props.textAlign === 'center' ? 'bg-brevo-blue text-white shadow' : 'text-gray-600 hover:bg-gray-200'}" 
                                           onclick="editor.updateElement('${element.id}', {textAlign: 'center'})">
                                        <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M7 21h10v-2H7v2zm-4-4h18v-2H3v2zm0-4h18v-2H3v2zm4-4h10v-2H7v2zm-4-4h18v-2H3v2z"/>
                                        </svg>
                                    </button>
                                    <button class="flex-1 p-2 rounded-md text-sm transition-all ${element.props.textAlign === 'right' ? 'bg-brevo-blue text-white shadow' : 'text-gray-600 hover:bg-gray-200'}" 
                                           onclick="editor.updateElement('${element.id}', {textAlign: 'right'})">
                                        <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M9 21h12v-2H9v2zm-6-4h18v-2H3v2zm0-4h18v-2H3v2zm6-4h12v-2H9v2zm-6-4h18v-2H3v2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>`;
                        break;

                    case 'image':
                        html = `
                            <div class="bg-gray-50 p-3 rounded-lg mb-4">
                                <div class="text-xs text-gray-600 mb-2 uppercase tracking-wide">Image actuelle</div>
                                <img src="${element.props.src}" class="w-full h-20 object-cover rounded-lg border border-gray-200 mb-3">
                                <button onclick="editor.changeImage('${element.id}')" 
                                        class="w-full p-2 bg-brevo-blue text-white rounded-lg hover:bg-brevo-blue-dark transition-colors text-sm font-medium">
                                    Changer l'image
                                </button>
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Texte alternatif</label>
                                <input type="text" class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brevo-blue focus:border-transparent" 
                                      value="${element.props.alt}" onchange="editor.updateElement('${element.id}', {alt: this.value})" placeholder="Description de l'image">
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Rayon des coins</label>
                                <select class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brevo-blue focus:border-transparent bg-white" 
                                       onchange="editor.updateElement('${element.id}', {borderRadius: this.value})">
                                    <option value="0px" ${element.props.borderRadius === '0px' ? 'selected' : ''}>Carré</option>
                                    <option value="4px" ${element.props.borderRadius === '4px' ? 'selected' : ''}>Légèrement arrondi</option>
                                    <option value="8px" ${element.props.borderRadius === '8px' ? 'selected' : ''}>Arrondi</option>
                                    <option value="12px" ${element.props.borderRadius === '12px' ? 'selected' : ''}>Très arrondi</option>
                                    <option value="50%" ${element.props.borderRadius === '50%' ? 'selected' : ''}>Circulaire</option>
                                </select>
                            </div>`;
                        break;

                    case 'button':
                        html = `
                            <div class="bg-gray-50 p-3 rounded-lg mb-4">
                                <div class="text-xs text-gray-600 mb-2 uppercase tracking-wide">Texte du bouton</div>
                                <input type="text" class="w-full p-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brevo-blue focus:border-transparent font-medium" 
                                      value="${element.props.text}" onchange="editor.updateElement('${element.id}', {text: this.value})" placeholder="Texte du bouton">
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Arrière-plan</label>
                                    <input type="color" class="w-full h-10 border border-gray-300 rounded-lg cursor-pointer" 
                                          value="${element.props.backgroundColor}" onchange="editor.updateElement('${element.id}', {backgroundColor: this.value})">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Texte</label>
                                    <input type="color" class="w-full h-10 border border-gray-300 rounded-lg cursor-pointer" 
                                          value="${element.props.color}" onchange="editor.updateElement('${element.id}', {color: this.value})">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Taille du bouton</label>
                                <select class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brevo-blue focus:border-transparent bg-white" 
                                       onchange="editor.updateButtonSize('${element.id}', this.value)">
                                    <option value="small">Petit (10px 20px)</option>
                                    <option value="medium" selected>Moyen (14px 28px)</option>
                                    <option value="large">Grand (18px 36px)</option>
                                </select>
                            </div>`;
                        break;

                    case 'divider':
                        html = `
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Épaisseur</label>
                                    <select class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brevo-blue focus:border-transparent bg-white" 
                                           onchange="editor.updateElement('${element.id}', {height: this.value})">
                                        <option value="1px" ${element.props.height === '1px' ? 'selected' : ''}>1px - Fin</option>
                                        <option value="2px" ${element.props.height === '2px' ? 'selected' : ''}>2px - Normal</option>
                                        <option value="3px" ${element.props.height === '3px' ? 'selected' : ''}>3px - Épais</option>
                                        <option value="5px" ${element.props.height === '5px' ? 'selected' : ''}>5px - Très épais</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Couleur</label>
                                    <input type="color" class="w-full h-10 border border-gray-300 rounded-lg cursor-pointer" 
                                          value="${element.props.backgroundColor}" onchange="editor.updateElement('${element.id}', {backgroundColor: this.value})">
                                </div>
                            </div>`;
                        break;
                }

                html += `
                    <div class="pt-4 border-t border-gray-200 mt-6">
                        <button class="w-full p-3 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors font-medium flex items-center justify-center gap-2" 
                                onclick="editor.deleteElement('${element.id}')">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Supprimer l'élément
                        </button>
                    </div>`;
                
                return html;
            }

            updateButtonSize(id, size) {
                const sizes = {
                    small: { padding: '10px 20px', fontSize: '14px' },
                    medium: { padding: '14px 28px', fontSize: '16px' },
                    large: { padding: '18px 36px', fontSize: '18px' }
                };
                this.updateElement(id, sizes[size]);
            }

            changeImage(elementId) {
                this.currentImageElement = this.elements.find(el => el.id === elementId);
                this.openImageModal();
            }

            togglePreview() {
                this.showPreview = !this.showPreview;
                const canvas = document.getElementById('emailCanvas');
                const btn = document.getElementById('previewBtn');

                if (this.showPreview) {
                    canvas.classList.add('shadow-2xl');
                    btn.classList.remove('bg-brevo-blue-light', 'text-brevo-blue');
                    btn.classList.add('bg-brevo-blue', 'text-white');
                    this.selectElement(null);
                    
                    // Masquer les contrôles d'édition
                    document.querySelectorAll('.canvas-element').forEach(el => {
                        el.style.pointerEvents = 'none';
                    });
                } else {
                    canvas.classList.remove('shadow-2xl');
                    btn.classList.remove('bg-brevo-blue', 'text-white');
                    btn.classList.add('bg-brevo-blue-light', 'text-brevo-blue');
                    
                    // Réactiver les contrôles d'édition
                    document.querySelectorAll('.canvas-element').forEach(el => {
                        el.style.pointerEvents = 'auto';
                    });
                }
            }

            toggleCode() {
                this.showCode = !this.showCode;
                const canvas = document.getElementById('emailCanvas');
                const codeViewer = document.getElementById('codeViewer');
                const btn = document.getElementById('codeBtn');

                if (this.showCode) {
                    canvas.classList.add('hidden');
                    codeViewer.classList.remove('hidden');
                    btn.classList.add('bg-green-200');
                    this.generateHTML();
                } else {
                    canvas.classList.remove('hidden');
                    codeViewer.classList.add('hidden');
                    btn.classList.remove('bg-green-200');
                }
            }

            generateHTML() {
                const codeContent = document.getElementById('codeContent');
                let html = `<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter</title>
    <style>
        body { margin: 0; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .email-container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .button:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        @media (max-width: 600px) {
            .email-container { padding: 20px; margin: 10px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
`;

                this.elements.forEach(element => {
                    html += '        ' + this.renderElement(element).replace('onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'"', 'class="button"') + '\n';
                });

                html += `    </div>
</body>
</html>`;
                
                codeContent.textContent = html;
            }

            downloadHTML() {
                this.generateHTML();
                const codeContent = document.getElementById('codeContent');
                const htmlContent = codeContent.textContent;
                
                const blob = new Blob([htmlContent], { type: 'text/html' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `newsletter-${new Date().toISOString().slice(0, 10)}.html`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        }

        // Fonctions globales pour le modal d'image
        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
            document.getElementById('imageUrl').value = '';
            document.getElementById('imageUpload').value = '';
        }

        function handleImageUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imageUrl').value = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        function confirmImageSelection() {
            const imageUrl = document.getElementById('imageUrl').value;
            if (!imageUrl) {
                alert('Veuillez sélectionner une image ou entrer une URL');
                return;
            }

            if (editor.currentImageElement) {
                // Modification d'une image existante
                editor.updateElement(editor.currentImageElement.id, { src: imageUrl });
                editor.currentImageElement = null;
            } else {
                // Ajout d'une nouvelle image
                editor.addElement('image', { src: imageUrl });
            }
            
            closeImageModal();
        }

        // Initialisation
        const editor = new EmailEditor();
    </script>
</body>
</html>