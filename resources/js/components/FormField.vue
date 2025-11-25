<template>
  <div v-show="isVisible" :class="containerClasses">
    <component
      v-for="(field, index) in field.fields"
      :key="field.attribute || index"
      :ref="(el) => setFieldRef(field.attribute, el)"
      :is="`form-${field.component}`"
      :resource-name="resourceName"
      :resource-id="resourceId"
      :field="field"
      :errors="errors"
      :show-help-text="showHelpText"
      :mode="mode"
      :via-resource="viaResource"
      :via-resource-id="viaResourceId"
      :via-relationship="viaRelationship"
      @field-changed="handleFieldChanged"
      @file-deleted="$emit('file-deleted')"
      @file-upload-started="$emit('file-upload-started')"
      @file-upload-finished="$emit('file-upload-finished')"
    />
  </div>
</template>

<script>
import { DependentFormField, HandlesValidationErrors } from 'laravel-nova';

export default {
  mixins: [DependentFormField, HandlesValidationErrors],

  props: [
    'resourceName',
    'resourceId',
    'field',
    'errors',
    'showHelpText',
    'mode',
    'viaResource',
    'viaResourceId',
    'viaRelationship',
  ],

  data() {
    return {
      dependentFieldValues: {},
      isVisible: false,
      cachedContextPrefix: null,
      contextDetected: false,
      fieldRefs: {},
    };
  },

  computed: {
    containerClasses() {
      if (this.field.applyToFields) {
        return [];
      }

      return [
        'nova-dependency-container',
        'space-y-4',
        'py-4',
        'px-6',
        'bg-gray-50',
        'dark:bg-gray-800',
        'rounded-lg',
        'border',
        'border-gray-200',
        'dark:border-gray-700',
      ];
    },
  },

  mounted() {
    this.detectFlexibleContextOnMount();
    this.applyFlexiblePrefixToChildFields();
    this.watchDependentFields();
    this.checkDependencies();
  },

  methods: {
    /**
     * Store a reference to a child field component (Vue 3 function ref pattern).
     * This is needed because Vue 3 doesn't support dynamic string refs in v-for.
     */
    setFieldRef(attribute, el) {
      if (el) {
        this.fieldRefs[attribute] = el;
      } else {
        delete this.fieldRefs[attribute];
      }
    },

    /**
     * Apply the Flexible field prefix to child field attributes.
     * This ensures child fields have the correct prefixed attribute when inside a Flexible layout,
     * which is necessary for form data to be saved correctly.
     */
    applyFlexiblePrefixToChildFields() {
      if (!this.cachedContextPrefix || !this.field.fields) {
        return;
      }

      this.field.fields.forEach(childField => {
        if (!childField.attribute) return;

        // Check if the attribute already has the prefix
        if (childField.attribute.startsWith(this.cachedContextPrefix)) {
          return;
        }

        // Apply the Flexible prefix
        childField.attribute = `${this.cachedContextPrefix}${childField.attribute}`;
      });
    },

    /**
     * Attempt to detect Flexible field context at mount time.
     * This is critical for proper event filtering in multi-group scenarios.
     */
    detectFlexibleContextOnMount() {
      // Method 1: Check container's own attribute
      const ownAttribute = this.field?.attribute || '';
      if (ownAttribute) {
        const prefix = this.extractPrefixFromAttribute(ownAttribute);
        if (prefix) {
          this.cachedContextPrefix = prefix;
          this.contextDetected = true;
          return;
        }
      }

      // Method 2: Check child field attributes
      const childFields = this.field?.fields || [];
      for (const child of childFields) {
        if (child.attribute) {
          const childPrefix = this.extractPrefixFromAttribute(child.attribute);
          if (childPrefix) {
            this.cachedContextPrefix = childPrefix;
            this.contextDetected = true;
            return;
          }
        }
      }

      // Method 3: Walk up the Vue component tree to find Flexible layout
      let parent = this.$parent;
      let depth = 0;
      const maxDepth = 20;

      while (parent && depth < maxDepth) {
        // Check for Flexible layout indicators
        // nova-flexible-content typically has these properties
        if (parent.layout !== undefined || parent.layoutIndex !== undefined || parent.groupIndex !== undefined) {
          const flexKey = parent.field?.attribute || parent.attribute || '';
          const index = parent.layoutIndex ?? parent.groupIndex ?? parent.index ?? 0;

          if (flexKey) {
            this.cachedContextPrefix = `${flexKey}__${index}__`;
            this.contextDetected = true;
            return;
          }
        }

        // Check parent's field for Flexible prefix pattern
        if (parent.field?.attribute) {
          const prefix = this.extractPrefixFromAttribute(parent.field.attribute);
          if (prefix) {
            this.cachedContextPrefix = prefix;
            this.contextDetected = true;
            return;
          }
        }

        parent = parent.$parent;
        depth++;
      }

      // Method 4: Check DOM for Flexible field wrapper
      this.$nextTick(() => {
        if (!this.contextDetected) {
          this.detectContextFromDOM();
        }
      });
    },

    /**
     * Detect Flexible context from DOM structure.
     */
    detectContextFromDOM() {
      try {
        let el = this.$el;
        let depth = 0;
        const maxDepth = 20;

        while (el && depth < maxDepth) {
          // Look for data attributes that might indicate Flexible context
          if (el.dataset) {
            const flexKey = el.dataset.flexibleKey || el.dataset.layoutKey;
            const flexIndex = el.dataset.flexibleIndex || el.dataset.layoutIndex;

            if (flexKey && flexIndex !== undefined) {
              this.cachedContextPrefix = `${flexKey}__${flexIndex}__`;
              this.contextDetected = true;
              return;
            }
          }

          // Look for input elements with prefixed names
          const inputs = el.querySelectorAll ? el.querySelectorAll('input, select, textarea') : [];
          for (const input of inputs) {
            const name = input.name || input.id;
            if (name) {
              const prefix = this.extractPrefixFromAttribute(name);
              if (prefix) {
                this.cachedContextPrefix = prefix;
                this.contextDetected = true;
                return;
              }
            }
          }

          el = el.parentElement;
          depth++;
        }
      } catch (e) {
        // Silently fail DOM detection
      }
    },

    /**
     * Set up watchers for dependent field changes.
     */
    watchDependentFields() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      Nova.$on('field-changed', this.handleFieldChanged);

      // Also set up DOM-based watching for Flexible fields where Nova events might not work
      this.$nextTick(() => {
        this.setupDOMWatching();
        this.loadInitialValuesFromDOM();
      });
    },

    /**
     * Set up DOM-based watching for field changes.
     * This is needed because fields inside Flexible layouts may not emit Nova events.
     */
    setupDOMWatching() {
      if (!this.cachedContextPrefix) return;

      const flexibleContainer = this.findFlexibleContainer();
      if (!flexibleContainer) {
        return;
      }

      // Watch for changes on select, input, and textarea elements
      const watchElements = flexibleContainer.querySelectorAll('select, input, textarea');
      watchElements.forEach(el => {
        el.addEventListener('change', this.handleDOMChange);
        el.addEventListener('input', this.handleDOMChange);
      });

      // Store reference for cleanup
      this.watchedElements = watchElements;
      this.flexibleContainer = flexibleContainer;

      // Also use MutationObserver to catch dynamically added elements
      this.setupMutationObserver(flexibleContainer);
    },

    /**
     * Set up MutationObserver to watch for DOM changes in the Flexible container.
     */
    setupMutationObserver(container) {
      if (this.mutationObserver) {
        this.mutationObserver.disconnect();
      }

      this.mutationObserver = new MutationObserver(() => {
        this.loadInitialValuesFromDOM();
      });

      this.mutationObserver.observe(container, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['value']
      });
    },

    /**
     * Find the Flexible layout container element.
     */
    findFlexibleContainer() {
      let el = this.$el;
      let depth = 0;
      const maxDepth = 20;

      while (el && depth < maxDepth) {
        // Look for Flexible layout markers
        if (el.classList && (
          el.classList.contains('flexible-group') ||
          el.classList.contains('nova-flexible-content-group') ||
          el.dataset?.flexibleGroup
        )) {
          return el;
        }

        // Look for parent with the same prefix in data attributes
        if (el.querySelector && this.cachedContextPrefix) {
          const prefixedElements = el.querySelectorAll(`[name^="${this.cachedContextPrefix}"], [id^="${this.cachedContextPrefix}"]`);
          if (prefixedElements.length > 1) {
            return el;
          }
        }

        el = el.parentElement;
        depth++;
      }

      // Fallback: return the closest form or field container
      return this.$el?.closest('form') || this.$el?.closest('[data-field-wrapper]') || document.body;
    },

    /**
     * Handle DOM change events from sibling fields.
     */
    handleDOMChange(event) {
      const element = event.target;
      const name = element.name || element.id || '';
      const value = element.type === 'checkbox' ? element.checked : element.value;

      if (!name) return;

      // Check if this element belongs to our Flexible context
      const elementPrefix = this.extractPrefixFromAttribute(name);
      if (this.cachedContextPrefix && elementPrefix && elementPrefix !== this.cachedContextPrefix) {
        return;
      }

      const baseAttribute = this.extractBaseAttribute(name);
      const isRelevantField = this.field.dependencies.some(dep => dep.field === baseAttribute);

      if (isRelevantField) {
        this.dependentFieldValues[name] = value;
        this.dependentFieldValues[baseAttribute] = value;
        this.checkDependencies();
      }
    },

    /**
     * Load initial values from DOM elements.
     */
    loadInitialValuesFromDOM() {
      if (!this.field.dependencies) return;

      for (const dependency of this.field.dependencies) {
        const fieldName = dependency.field;
        const prefixedName = this.cachedContextPrefix ? `${this.cachedContextPrefix}${fieldName}` : fieldName;

        const selectors = [
          `[name="${prefixedName}"]`,
          `[name="${fieldName}"]`,
          `[id="${prefixedName}"]`,
          `[id="${fieldName}"]`,
          `[name$="__${fieldName}"]`,
          `[name$="[${fieldName}]"]`,
        ];

        for (const selector of selectors) {
          try {
            const element = document.querySelector(selector);
            if (element) {
              const value = element.type === 'checkbox' ? element.checked : element.value;

              if (value !== undefined && value !== null && value !== '') {
                this.dependentFieldValues[element.name || element.id] = value;
                this.dependentFieldValues[fieldName] = value;
                this.checkDependencies();
                break;
              }
            }
          } catch (e) {
            // Invalid selector, skip
          }
        }
      }
    },

    /**
     * Handle Nova field-changed events.
     */
    handleFieldChanged(event) {
      const fullAttribute = event.field.attribute;
      const eventPrefix = this.extractPrefixFromAttribute(fullAttribute);
      const baseAttribute = this.extractBaseAttribute(fullAttribute);

      const isRelevantField = this.field.dependencies.some(dep => dep.field === baseAttribute);

      // If we have a detected context, filter events strictly
      if (this.contextDetected && this.cachedContextPrefix) {
        if (eventPrefix && eventPrefix !== this.cachedContextPrefix) {
          return;
        }
      } else if (eventPrefix && isRelevantField) {
        this.cachedContextPrefix = eventPrefix;
        this.contextDetected = true;
      }

      this.dependentFieldValues[fullAttribute] = event.value;

      if (!eventPrefix || (this.contextDetected && this.cachedContextPrefix === eventPrefix)) {
        this.dependentFieldValues[baseAttribute] = event.value;
      }

      this.checkDependencies();
    },

    /**
     * Extract the prefix from a full attribute.
     * Supports multiple formats:
     * - "overlay_items__0__field_name" -> "overlay_items__0__"
     * - "overlay_items[0][field_name]" -> "overlay_items[0]["
     * - "cSkn6uKpVHMkMLmI__field_name" -> "cSkn6uKpVHMkMLmI__" (random key format)
     */
    extractPrefixFromAttribute(attribute) {
      if (!attribute) return null;

      // Pattern 1: Double underscore with numeric index
      const numericUnderscoreMatch = attribute.match(/^(.+__\d+__)/);
      if (numericUnderscoreMatch) {
        return numericUnderscoreMatch[1];
      }

      // Pattern 2: Bracket format
      const bracketMatch = attribute.match(/^(.+\[\d+\]\[)/);
      if (bracketMatch) {
        return bracketMatch[1];
      }

      // Pattern 3: Random key format (nova-flexible-content)
      const randomKeyMatch = attribute.match(/^([a-zA-Z0-9]+__)/);
      if (randomKeyMatch) {
        return randomKeyMatch[1];
      }

      return null;
    },

    /**
     * Extract the base attribute name from a potentially prefixed Flexible field attribute.
     * Supports multiple formats:
     * - "overlay_items__0__type" -> "type"
     * - "overlay_items[0][type]" -> "type"
     * - "cSkn6uKpVHMkMLmI__type" -> "type" (random key format)
     */
    extractBaseAttribute(attribute) {
      if (!attribute) return null;

      // Pattern 1: Double underscore with numeric index
      const numericUnderscoreMatch = attribute.match(/^.+__\d+__(.+)$/);
      if (numericUnderscoreMatch) {
        return numericUnderscoreMatch[1];
      }

      // Pattern 2: Bracket format
      const bracketMatch = attribute.match(/^.+\[\d+\]\[(.+)\]$/);
      if (bracketMatch) {
        return bracketMatch[1];
      }

      // Pattern 3: Random key format (nova-flexible-content)
      const randomKeyMatch = attribute.match(/^[a-zA-Z0-9]+__(.+)$/);
      if (randomKeyMatch) {
        return randomKeyMatch[1];
      }

      return attribute;
    },

    /**
     * Check if all dependencies are satisfied and update visibility.
     */
    checkDependencies() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      let satisfied = true;

      for (const dependency of this.field.dependencies) {
        const fieldValue = this.getFieldValue(dependency.field);

        if (dependency.hasOwnProperty('value')) {
          if (Array.isArray(dependency.value)) {
            if (!dependency.value.includes(fieldValue)) {
              satisfied = false;
              break;
            }
          } else if (fieldValue != dependency.value) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('not')) {
          if (Array.isArray(dependency.not)) {
            if (dependency.not.includes(fieldValue)) {
              satisfied = false;
              break;
            }
          } else if (fieldValue == dependency.not) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('notIn')) {
          if (dependency.notIn.includes(fieldValue)) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('empty')) {
          if (dependency.empty && !this.isEmpty(fieldValue)) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('notEmpty')) {
          if (dependency.notEmpty && this.isEmpty(fieldValue)) {
            satisfied = false;
            break;
          }
        } else if (dependency.hasOwnProperty('nullOrZero')) {
          if (dependency.nullOrZero && fieldValue != null && fieldValue != 0 && fieldValue != '0') {
            satisfied = false;
            break;
          }
        }
      }

      this.isVisible = satisfied;

      if (this.field.applyToFields && this.field.fields) {
        this.field.fields.forEach(field => {
          Nova.$emit('nova-dependency-container:toggle-field', {
            field: field.attribute,
            visible: satisfied,
          });
        });
      }
    },

    /**
     * Get the current value of a dependent field.
     */
    getFieldValue(fieldAttribute) {
      if (this.dependentFieldValues.hasOwnProperty(fieldAttribute)) {
        return this.dependentFieldValues[fieldAttribute];
      }

      if (this.cachedContextPrefix) {
        const prefixedAttribute = `${this.cachedContextPrefix}${fieldAttribute}`;
        if (this.dependentFieldValues.hasOwnProperty(prefixedAttribute)) {
          return this.dependentFieldValues[prefixedAttribute];
        }

        const alternativeFormats = this.getFlexibleAttributeFormats(this.cachedContextPrefix, fieldAttribute);
        for (const format of alternativeFormats) {
          if (this.dependentFieldValues.hasOwnProperty(format)) {
            return this.dependentFieldValues[format];
          }
        }
      }

      const field = this.findFieldByAttribute(fieldAttribute);
      if (field) {
        return field.value;
      }

      return null;
    },

    /**
     * Find a field component by its attribute name.
     */
    findFieldByAttribute(attribute) {
      const allFields = this.getAllFields();

      const exactMatch = allFields.find(f => f.field && f.field.attribute === attribute);
      if (exactMatch) {
        return exactMatch;
      }

      if (this.cachedContextPrefix) {
        const prefixedAttribute = `${this.cachedContextPrefix}${attribute}`;
        const prefixedMatch = allFields.find(f => f.field && f.field.attribute === prefixedAttribute);
        if (prefixedMatch) {
          return prefixedMatch;
        }

        const alternativeFormats = this.getFlexibleAttributeFormats(this.cachedContextPrefix, attribute);
        for (const format of alternativeFormats) {
          const match = allFields.find(f => f.field && f.field.attribute === format);
          if (match) {
            return match;
          }
        }
      }

      const suffixMatch = allFields.find(f => {
        if (!f.field || !f.field.attribute) return false;
        const attr = f.field.attribute;
        return attr.endsWith(`__${attribute}`) ||
               attr.endsWith(`][${attribute}]`) ||
               attr.endsWith(`[${attribute}]`);
      });

      return suffixMatch || null;
    },

    /**
     * Get all rendered fields from the Nova form.
     */
    getAllFields() {
      if (Nova.$parent?.$refs?.fields) {
        return Nova.$parent.$refs.fields;
      }

      let parent = this.$parent;
      let maxDepth = 10;

      while (parent && maxDepth-- > 0) {
        if (parent.$refs?.fields && Array.isArray(parent.$refs.fields)) {
          return parent.$refs.fields;
        }
        if (parent.fields && Array.isArray(parent.fields)) {
          return parent.fields.map(f => ({ field: f }));
        }
        parent = parent.$parent;
      }

      return [];
    },

    /**
     * Generate alternative attribute formats for Flexible fields.
     */
    getFlexibleAttributeFormats(prefix, attribute) {
      const formats = [];

      const underscoreMatch = prefix.match(/^(.+)__(\d+)__$/);
      const bracketMatch = prefix.match(/^(.+)\[(\d+)\]\[$/);

      if (underscoreMatch) {
        const [, key, index] = underscoreMatch;
        formats.push(`${key}[${index}][${attribute}]`);
        formats.push(`${key}_${index}_${attribute}`);
      }

      if (bracketMatch) {
        const [, key, index] = bracketMatch;
        formats.push(`${key}__${index}__${attribute}`);
        formats.push(`${key}_${index}_${attribute}`);
      }

      return formats;
    },

    /**
     * Check if a value is empty.
     */
    isEmpty(value) {
      return value === null ||
             value === undefined ||
             value === '' ||
             (Array.isArray(value) && value.length === 0) ||
             (typeof value === 'object' && Object.keys(value).length === 0);
    },

    /**
     * Fill form data with child field values.
     */
    fill(formData) {
      if (!this.isVisible) {
        return;
      }

      if (this.field.fields) {
        this.field.fields.forEach(field => {
          const fieldComponent = this.fieldRefs[field.attribute];

          if (fieldComponent && typeof fieldComponent.fill === 'function') {
            fieldComponent.fill(formData);
          }
        });
      }
    },
  },

  beforeUnmount() {
    Nova.$off('field-changed', this.handleFieldChanged);

    if (this.watchedElements) {
      this.watchedElements.forEach(el => {
        el.removeEventListener('change', this.handleDOMChange);
        el.removeEventListener('input', this.handleDOMChange);
      });
    }

    if (this.mutationObserver) {
      this.mutationObserver.disconnect();
    }

    this.fieldRefs = {};
  },
};
</script>

<style lang="scss" scoped>
.nova-dependency-container {
  transition: all 0.3s ease;
}
</style>
