# WT Update JShopping prices and quantity Joomla task scheduler plugin
The plugin allows you to update prices and balances of goods and dependent JoomShopping attributes from a CSV file.
# How to
This task plugin assumes the following data acquisition scenario. 
You are creating a CSV file in `utf-8` encoding with 3 columns:
- product identifier (EAN, manufacturer number or real EAN)
- quantity
- product price

and put the created file in the selected folder on the site.

**The order of the columns is important.**

The products are updated using a simple SQL `UPDATE`. If several products with the same EAN / manufacturer code / readl EAN are found, the changes will apply to all.

Updating prices and quantities for dependent attributes works in a similar way: if several dependent attributes with the same product EAN / manufacturer code/ real EAN are found, then the changes will apply to all, even if they are in different products.

Keep track of the uniqueness of the product EAN / manufacturer code / real EAN when administering the site.

If the file has not changed since the last start of the task, the data will not be updated.
