<template>
  <div v-show="isVisible" :class="containerClasses">
    <component
      v-for="(field, index) in field.fields"
      :key="index"
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
    console.log('[NovaDependencyContainer] Mounted with field:', this.field);
    console.log('[NovaDependencyContainer] Dependencies:', this.field.dependencies);
    this.detectFlexibleContextOnMount();
    this.watchDependentFields();
    this.checkDependencies();
  },

  methods: {
    /**
     * Attempt to detect Flexible field context at mount time.
     * This is critical for proper event filtering in multi-group scenarios.
     */
    detectFlexibleContextOnMount() {
      console.log('[NovaDependencyContainer] detectFlexibleContextOnMount called');

      // Method 1: Check container's own attribute
      const ownAttribute = this.field?.attribute || '';
      console.log('[NovaDependencyContainer] Container attribute:', ownAttribute);
      if (ownAttribute) {
        const prefix = this.extractPrefixFromAttribute(ownAttribute);
        if (prefix) {
          console.log('[NovaDependencyContainer] Detected context from own attribute:', prefix);
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

    watchDependentFields() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        console.log('[NovaDependencyContainer] No dependencies, showing field');
        this.isVisible = true;
        return;
      }

      console.log('[NovaDependencyContainer] Watching for field-changed events');
      Nova.$on('field-changed', this.handleFieldChanged);
    },

    handleFieldChanged(event) {
      console.log('[NovaDependencyContainer] field-changed event received:', event);
      const fullAttribute = event.field.attribute;
      const eventPrefix = this.extractPrefixFromAttribute(fullAttribute);
      const baseAttribute = this.extractBaseAttribute(fullAttribute);

      console.log('[NovaDependencyContainer] Event details:', {
        fullAttribute,
        eventPrefix,
        baseAttribute,
        value: event.value,
        cachedContextPrefix: this.cachedContextPrefix,
        contextDetected: this.contextDetected
      });

      // Check if this event is for a field we depend on
      const isRelevantField = this.field.dependencies.some(dep => dep.field === baseAttribute);
      console.log('[NovaDependencyContainer] Is relevant field:', isRelevantField, 'Dependencies:', this.field.dependencies.map(d => d.field));

      // If we have a detected context, filter events strictly
      if (this.contextDetected && this.cachedContextPrefix) {
        if (eventPrefix && eventPrefix !== this.cachedContextPrefix) {
          // Event is from a different Flexible group, ignore it
          return;
        }
      } else if (eventPrefix && isRelevantField) {
        // No context detected yet - this relevant event can set our context
        // Only claim context from events that match our dependencies
        this.cachedContextPrefix = eventPrefix;
        this.contextDetected = true;
      }

      // Store by full attribute (always safe, no cross-contamination)
      this.dependentFieldValues[fullAttribute] = event.value;

      // Only store by base attribute if we're sure about our context
      // or if this is a non-Flexible field (no prefix)
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
     * - "cSkn6uKpVHMkMLmI__" -> "cSkn6uKpVHMkMLmI__" (prefix only)
     */
    extractPrefixFromAttribute(attribute) {
      if (!attribute) return null;

      // Pattern 1: Double underscore with numeric index (e.g., "overlay_items__0__field_name")
      const numericUnderscoreMatch = attribute.match(/^(.+__\d+__)/);
      if (numericUnderscoreMatch) {
        return numericUnderscoreMatch[1];
      }

      // Pattern 2: Bracket format (e.g., "overlay_items[0][field_name]")
      const bracketMatch = attribute.match(/^(.+\[\d+\]\[)/);
      if (bracketMatch) {
        return bracketMatch[1];
      }

      // Pattern 3: Random key format (e.g., "cSkn6uKpVHMkMLmI__field_name" or just "cSkn6uKpVHMkMLmI__")
      // This handles nova-flexible-content's random key format
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

      // Pattern 1: Double underscore with numeric index (e.g., "overlay_items__0__field_name")
      const numericUnderscoreMatch = attribute.match(/^.+__\d+__(.+)$/);
      if (numericUnderscoreMatch) {
        return numericUnderscoreMatch[1];
      }

      // Pattern 2: Bracket format (e.g., "overlay_items[0][field_name]")
      const bracketMatch = attribute.match(/^.+\[\d+\]\[(.+)\]$/);
      if (bracketMatch) {
        return bracketMatch[1];
      }

      // Pattern 3: Random key format (e.g., "cSkn6uKpVHMkMLmI__type")
      // This handles nova-flexible-content's random key format
      const randomKeyMatch = attribute.match(/^[a-zA-Z0-9]+__(.+)$/);
      if (randomKeyMatch) {
        return randomKeyMatch[1];
      }

      return attribute;
    },

    checkDependencies() {
      console.log('[NovaDependencyContainer] checkDependencies called');
      console.log('[NovaDependencyContainer] Cached values:', this.dependentFieldValues);

      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      let satisfied = true;

      for (const dependency of this.field.dependencies) {
        const fieldValue = this.getFieldValue(dependency.field);
        console.log('[NovaDependencyContainer] Checking dependency:', dependency.field, '=', dependency.value, ', current value:', fieldValue);

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
      console.log('[NovaDependencyContainer] Setting isVisible to:', satisfied);

      if (this.field.applyToFields && this.field.fields) {
        this.field.fields.forEach(field => {
          Nova.$emit('nova-dependency-container:toggle-field', {
            field: field.attribute,
            visible: satisfied,
          });
        });
      }
    },

    getFieldValue(fieldAttribute) {
      // First check exact match in cache
      if (this.dependentFieldValues.hasOwnProperty(fieldAttribute)) {
        return this.dependentFieldValues[fieldAttribute];
      }

      // Check for prefixed version in cache (for Flexible field contexts)
      if (this.cachedContextPrefix) {
        const prefixedAttribute = `${this.cachedContextPrefix}${fieldAttribute}`;
        if (this.dependentFieldValues.hasOwnProperty(prefixedAttribute)) {
          return this.dependentFieldValues[prefixedAttribute];
        }

        // Try alternative formats
        const alternativeFormats = this.getFlexibleAttributeFormats(this.cachedContextPrefix, fieldAttribute);
        for (const format of alternativeFormats) {
          if (this.dependentFieldValues.hasOwnProperty(format)) {
            return this.dependentFieldValues[format];
          }
        }
      }

      // Fallback to finding field in DOM
      const field = this.findFieldByAttribute(fieldAttribute);
      if (field) {
        return field.value;
      }

      return null;
    },

    findFieldByAttribute(attribute) {
      const allFields = this.getAllFields();

      // First try exact match
      const exactMatch = allFields.find(f => f.field && f.field.attribute === attribute);
      if (exactMatch) {
        return exactMatch;
      }

      // For Flexible fields: resolve attribute relative to current context
      if (this.cachedContextPrefix) {
        // Try to find field with same prefix (within same Flexible group)
        const prefixedAttribute = `${this.cachedContextPrefix}${attribute}`;
        const prefixedMatch = allFields.find(f => f.field && f.field.attribute === prefixedAttribute);
        if (prefixedMatch) {
          return prefixedMatch;
        }

        // Also try alternative Flexible attribute formats
        const alternativeFormats = this.getFlexibleAttributeFormats(this.cachedContextPrefix, attribute);
        for (const format of alternativeFormats) {
          const match = allFields.find(f => f.field && f.field.attribute === format);
          if (match) {
            return match;
          }
        }
      }

      // Fallback: find any field that ends with the attribute name (for nested contexts)
      const suffixMatch = allFields.find(f => {
        if (!f.field || !f.field.attribute) return false;
        const attr = f.field.attribute;
        // Match patterns like: prefix__attribute, prefix[index][attribute]
        return attr.endsWith(`__${attribute}`) ||
               attr.endsWith(`][${attribute}]`) ||
               attr.endsWith(`[${attribute}]`);
      });

      return suffixMatch || null;
    },

    /**
     * Get all rendered fields from the Nova form.
     * Handles both standard Nova forms and nested Flexible field contexts.
     */
    getAllFields() {
      // Try Nova's global field reference first
      if (Nova.$parent?.$refs?.fields) {
        return Nova.$parent.$refs.fields;
      }

      // Walk up the component tree to find fields
      let parent = this.$parent;
      let maxDepth = 10;

      while (parent && maxDepth-- > 0) {
        // Check for fields in parent's refs
        if (parent.$refs?.fields && Array.isArray(parent.$refs.fields)) {
          return parent.$refs.fields;
        }
        // Check for fields array directly on parent
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

      // Extract the base key and index from the prefix
      const underscoreMatch = prefix.match(/^(.+)__(\d+)__$/);
      const bracketMatch = prefix.match(/^(.+)\[(\d+)\]\[$/);

      if (underscoreMatch) {
        const [, key, index] = underscoreMatch;
        // Generate bracket format alternative
        formats.push(`${key}[${index}][${attribute}]`);
        // Single underscore variant
        formats.push(`${key}_${index}_${attribute}`);
      }

      if (bracketMatch) {
        const [, key, index] = bracketMatch;
        // Generate underscore format alternative
        formats.push(`${key}__${index}__${attribute}`);
        formats.push(`${key}_${index}_${attribute}`);
      }

      return formats;
    },

    isEmpty(value) {
      return value === null ||
             value === undefined ||
             value === '' ||
             (Array.isArray(value) && value.length === 0) ||
             (typeof value === 'object' && Object.keys(value).length === 0);
    },

    fill(formData) {
      if (!this.isVisible) {
        return;
      }

      if (this.field.fields) {
        this.field.fields.forEach(field => {
          const fieldComponent = this.$refs[`field-${field.attribute}`];
          if (fieldComponent && typeof fieldComponent.fill === 'function') {
            fieldComponent.fill(formData);
          }
        });
      }
    },
  },

  beforeUnmount() {
    Nova.$off('field-changed', this.handleFieldChanged);
  },
};
</script>

<style lang="scss" scoped>
.nova-dependency-container {
  transition: all 0.3s ease;
}
</style>
