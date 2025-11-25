<template>
  <div v-show="isVisible" :class="containerClasses">
    <component
      v-for="(field, index) in field.fields"
      :key="index"
      :is="`detail-${field.component}`"
      :resource-name="resourceName"
      :resource-id="resourceId"
      :field="field"
    />
  </div>
</template>

<script>
export default {
  props: ['resourceName', 'resourceId', 'field'],

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
    this.checkDependencies();
  },

  methods: {
    checkDependencies() {
      if (!this.field.dependencies || this.field.dependencies.length === 0) {
        this.isVisible = true;
        return;
      }

      let satisfied = true;

      for (const dependency of this.field.dependencies) {
        const field = this.findFieldByAttribute(dependency.field);
        if (!field) {
          satisfied = false;
          break;
        }

        const fieldValue = field.value;

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
    },

    findFieldByAttribute(attribute) {
      const allFields = this.getAllFields();

      // First try exact match
      const exactMatch = allFields.find(f => f.attribute === attribute);
      if (exactMatch) {
        return exactMatch;
      }

      // For Flexible fields: resolve attribute relative to current context
      const contextPrefix = this.getFlexibleContextPrefix();
      if (contextPrefix) {
        // Try to find field with same prefix (within same Flexible group)
        const prefixedAttribute = `${contextPrefix}${attribute}`;
        const prefixedMatch = allFields.find(f => f.attribute === prefixedAttribute);
        if (prefixedMatch) {
          return prefixedMatch;
        }

        // Also try alternative Flexible attribute formats
        const alternativeFormats = this.getFlexibleAttributeFormats(contextPrefix, attribute);
        for (const format of alternativeFormats) {
          const match = allFields.find(f => f.attribute === format);
          if (match) {
            return match;
          }
        }
      }

      // Fallback: find any field that ends with the attribute name (for nested contexts)
      const suffixMatch = allFields.find(f => {
        if (!f.attribute) return false;
        const attr = f.attribute;
        // Match patterns like: prefix__attribute, prefix[index][attribute]
        return attr.endsWith(`__${attribute}`) ||
               attr.endsWith(`][${attribute}]`) ||
               attr.endsWith(`[${attribute}]`);
      });

      return suffixMatch || null;
    },

    /**
     * Get all fields from the parent context.
     * Handles both standard Nova detail views and nested Flexible field contexts.
     */
    getAllFields() {
      // Walk up the component tree to find fields
      let parent = this.$parent;
      let maxDepth = 10;

      while (parent && maxDepth-- > 0) {
        // Check for fields array directly on parent
        if (parent.fields && Array.isArray(parent.fields)) {
          return parent.fields;
        }
        // Check for fields in parent's refs
        if (parent.$refs?.fields && Array.isArray(parent.$refs.fields)) {
          return parent.$refs.fields.map(f => f.field || f);
        }
        parent = parent.$parent;
      }

      return [];
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
     * Detect the Flexible field context prefix from the container's own field attribute.
     * Flexible fields use prefixes like: flexible_key__index__ or flexible_key[index]
     */
    getFlexibleContextPrefix() {
      // Return cached prefix if available
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
  },
};
</script>

<style lang="scss" scoped>
.nova-dependency-container {
  transition: all 0.3s ease;
}
</style>