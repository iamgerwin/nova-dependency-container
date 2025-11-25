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
    this.detectFlexibleContextOnMount();
    this.checkDependencies();
  },

  methods: {
    /**
     * Attempt to detect Flexible field context at mount time.
     */
    detectFlexibleContextOnMount() {
      // Method 1: Check container's own attribute
      const ownAttribute = this.field?.attribute || '';
      if (ownAttribute) {
        const prefix = this.extractPrefixFromAttribute(ownAttribute);
        if (prefix) {
          this.cachedContextPrefix = prefix;
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
        if (parent.layout !== undefined || parent.layoutIndex !== undefined || parent.groupIndex !== undefined) {
          const flexKey = parent.field?.attribute || parent.attribute || '';
          const index = parent.layoutIndex ?? parent.groupIndex ?? parent.index ?? 0;

          if (flexKey) {
            this.cachedContextPrefix = `${flexKey}__${index}__`;
            return;
          }
        }

        // Check parent's field for Flexible prefix pattern
        if (parent.field?.attribute) {
          const prefix = this.extractPrefixFromAttribute(parent.field.attribute);
          if (prefix) {
            this.cachedContextPrefix = prefix;
            return;
          }
        }

        parent = parent.$parent;
        depth++;
      }

      // Method 4: Check all fields to find our context
      this.$nextTick(() => {
        if (!this.cachedContextPrefix) {
          const allFields = this.getAllFields();
          for (const f of allFields) {
            if (f.attribute) {
              const prefix = this.extractPrefixFromAttribute(f.attribute);
              if (prefix) {
                this.cachedContextPrefix = prefix;
                this.checkDependencies(); // Re-check with detected context
                return;
              }
            }
          }
        }
      });
    },

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
      if (this.cachedContextPrefix) {
        // Try to find field with same prefix (within same Flexible group)
        const prefixedAttribute = `${this.cachedContextPrefix}${attribute}`;
        const prefixedMatch = allFields.find(f => f.attribute === prefixedAttribute);
        if (prefixedMatch) {
          return prefixedMatch;
        }

        // Also try alternative Flexible attribute formats
        const alternativeFormats = this.getFlexibleAttributeFormats(this.cachedContextPrefix, attribute);
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
