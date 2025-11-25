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
    this.watchDependentFields();
    this.checkDependencies();
  },

  methods: {
    watchDependentFields() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      Nova.$on('field-changed', this.handleFieldChanged);
    },

    handleFieldChanged(event) {
      const fullAttribute = event.field.attribute;
      const eventPrefix = this.extractPrefixFromAttribute(fullAttribute);
      const currentPrefix = this.getFlexibleContextPrefix();

      // For Flexible fields: only process events from the same group context
      // If both have prefixes, they must match; if neither has prefix, process normally
      if (eventPrefix && currentPrefix) {
        if (eventPrefix !== currentPrefix) {
          // Event is from a different Flexible group, ignore it
          return;
        }
      }

      this.dependentFieldValues[fullAttribute] = event.value;

      // Also store by base attribute name (without Flexible prefix) for easier lookup
      const baseAttribute = this.extractBaseAttribute(fullAttribute);
      if (baseAttribute && baseAttribute !== fullAttribute) {
        this.dependentFieldValues[baseAttribute] = event.value;

        // Cache the context prefix from incoming events for better Flexible field detection
        if (!this.cachedContextPrefix && eventPrefix) {
          this.cachedContextPrefix = eventPrefix;
        }
      }

      this.checkDependencies();
    },

    /**
     * Extract the prefix (e.g., "overlay_items__0__") from a full attribute.
     */
    extractPrefixFromAttribute(attribute) {
      if (!attribute) return null;

      // Pattern 1: Double underscore format (e.g., "overlay_items__0__field_name")
      const underscoreMatch = attribute.match(/^(.+__\d+__)/);
      if (underscoreMatch) {
        return underscoreMatch[1];
      }

      // Pattern 2: Bracket format (e.g., "overlay_items[0][field_name]")
      const bracketMatch = attribute.match(/^(.+\[\d+\]\[)/);
      if (bracketMatch) {
        return bracketMatch[1];
      }

      return null;
    },

    /**
     * Extract the base attribute name from a potentially prefixed Flexible field attribute.
     * e.g., "overlay_items__0__type" -> "type"
     *       "overlay_items[0][type]" -> "type"
     */
    extractBaseAttribute(attribute) {
      if (!attribute) return null;

      // Pattern 1: Double underscore format (e.g., "overlay_items__0__field_name")
      const underscoreMatch = attribute.match(/^.+__\d+__(.+)$/);
      if (underscoreMatch) {
        return underscoreMatch[1];
      }

      // Pattern 2: Bracket format (e.g., "overlay_items[0][field_name]")
      const bracketMatch = attribute.match(/^.+\[\d+\]\[(.+)\]$/);
      if (bracketMatch) {
        return bracketMatch[1];
      }

      return attribute;
    },

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

    getFieldValue(fieldAttribute) {
      // First check exact match in cache
      if (this.dependentFieldValues.hasOwnProperty(fieldAttribute)) {
        return this.dependentFieldValues[fieldAttribute];
      }

      // Check for prefixed version in cache (for Flexible field contexts)
      const contextPrefix = this.getFlexibleContextPrefix();
      if (contextPrefix) {
        const prefixedAttribute = `${contextPrefix}${fieldAttribute}`;
        if (this.dependentFieldValues.hasOwnProperty(prefixedAttribute)) {
          return this.dependentFieldValues[prefixedAttribute];
        }

        // Try alternative formats
        const alternativeFormats = this.getFlexibleAttributeFormats(contextPrefix, fieldAttribute);
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
      const contextPrefix = this.getFlexibleContextPrefix();
      if (contextPrefix) {
        // Try to find field with same prefix (within same Flexible group)
        const prefixedAttribute = `${contextPrefix}${attribute}`;
        const prefixedMatch = allFields.find(f => f.field && f.field.attribute === prefixedAttribute);
        if (prefixedMatch) {
          return prefixedMatch;
        }

        // Also try alternative Flexible attribute formats
        const alternativeFormats = this.getFlexibleAttributeFormats(contextPrefix, attribute);
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
     * Detect the Flexible field context prefix from the container's own field attribute.
     * Flexible fields use prefixes like: flexible_key__index__ or flexible_key[index]
     */
    getFlexibleContextPrefix() {
      // Return cached prefix if available (detected from field-changed events)
      if (this.cachedContextPrefix) {
        return this.cachedContextPrefix;
      }

      // Check if this container has a prefixed attribute (indicating it's inside a Flexible field)
      const ownAttribute = this.field?.attribute || '';

      // Pattern 1: Double underscore format (e.g., "overlay_items__0__field_name")
      const underscoreMatch = ownAttribute.match(/^(.+__\d+__)/);
      if (underscoreMatch) {
        this.cachedContextPrefix = underscoreMatch[1];
        return this.cachedContextPrefix;
      }

      // Pattern 2: Bracket format (e.g., "overlay_items[0][field_name]")
      const bracketMatch = ownAttribute.match(/^(.+\[\d+\]\[)/);
      if (bracketMatch) {
        this.cachedContextPrefix = bracketMatch[1];
        return this.cachedContextPrefix;
      }

      // Try to detect from child field attributes (inside the container)
      const childFields = this.field?.fields || [];
      for (const child of childFields) {
        if (child.attribute) {
          const childPrefix = this.extractPrefixFromAttribute(child.attribute);
          if (childPrefix) {
            this.cachedContextPrefix = childPrefix;
            return this.cachedContextPrefix;
          }
        }
      }

      // Try to detect from cached dependent field values
      for (const attr of Object.keys(this.dependentFieldValues)) {
        const prefix = this.extractPrefixFromAttribute(attr);
        if (prefix) {
          this.cachedContextPrefix = prefix;
          return this.cachedContextPrefix;
        }
      }

      return null;
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