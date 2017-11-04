{capture name=path}{l s='My Customized Products'}{/capture}
{include file="$tpl_dir./errors.tpl"}
<section class="pdp-my-customzied">
    <table class="table table-bordered stock-management-on">
        <thead>
            <tr>
                <th>{l s='PRODUCT'}</th>
                <th>{l s='PRICE'}</th>
                <th>{l s='STATUS'}</th>
            </tr>
        </thead>
        <tbody>
            {if $orders && count($orders)}
                {foreach from=$orders key='obj' item='value'}
                    {foreach from=$value.history item=stateObj name="orderStates"}
                        {assign var=state value=$stateObj}
                    {/foreach}
                    {foreach from=$value.products item=product name=pro}
                        <tr class="item">
                            <td>
                                <br>
                                <label>{$product.product_name|escape:'html':'UTF-8'}</label>
                                <br>
                                <label>{l s='Reference: '}</label>
                                <label>{$product.product_reference|escape:'html':'UTF-8'}</label>
                            </td>             
                            <td>
                                <label class="price">
                                    {if isset($customizedDatas.$productId.$productAttributeId)}
                                        {if $group_use_tax}
                                            {convertPriceWithCurrency price=$product.total_customization_wt currency=$currency}
                                        {else}
                                            {convertPriceWithCurrency price=$product.total_customization currency=$currency}
                                        {/if}
                                    {else}
                                        {if $group_use_tax}
                                            {convertPriceWithCurrency price=$product.total_price_tax_incl currency=$currency}
                                        {else}
                                            {convertPriceWithCurrency price=$product.total_price_tax_excl currency=$currency}
                                        {/if}
                                    {/if}
                                </label>
                            </td>
                            <td>
                                <span{if isset($state.color) && $state.color} style="background-color:{$state.color|escape:'html':'UTF-8'}; border-color:{$state.color|escape:'html':'UTF-8'};"{/if} class="label{if isset($state.color) && Tools::getBrightness($state.color) > 128} dark{/if}">{$state.ostate_name|escape:'html':'UTF-8'}</span>
                            </td>
                        </tr>
                        {foreach $product.customizedDatas  as $customizationPerAddress}
                            {foreach $customizationPerAddress as $customizationId => $customization}
                                <tr class="alternate_item">
                                    <td colspan="3">
                                        {foreach from=$customization.datas key='type' item='datas'}
                                            {if $type == $CUSTOMIZE_FILE}
                                                <ul class="customizationUploaded">
                                                    {foreach from=$datas item='data'}
                                                        <li><img src="{$pic_dir}{$data.value}_small" alt="" class="customizationUploaded" /></li>
                                                        {/foreach}
                                                </ul>
                                            {elseif $type == $CUSTOMIZE_TEXTFIELD}
                                                <ul class="typedText">{counter start=0 print=false}
                                                    {foreach from=$datas item='data'}
                                                        {if $data.name == $PDPEdit}
                                                            <li>{$data.value}</li>
                                                            {else}
                                                                {assign var='customizationFieldName' value="Text #"|cat:$data.id_customization_field}
                                                            <li>{$data.name|default:$customizationFieldName} : {$data.value}</li>
                                                            {/if}
                                                        {/foreach}
                                                </ul>
                                            {/if}
                                        {/foreach}
                                    </td>
                                </tr>
                            {/foreach}
                        {/foreach}
                    {/foreach}
                {/foreach}
            {/if}
            {if $guestdesigns && count($guestdesigns)}
                {foreach from=$guestdesigns key='obj' item='value'}
                    <tr class="item">
                        <td>
                            <br>
                            <label>{$value.product.name|escape:'html':'UTF-8'}</label>
                            <br>
                            <label>{l s='Reference: '}</label>
                            <label>{$value.product.reference|escape:'html':'UTF-8'}</label>
                            <div>
                                {$value.content_html}
                            </div>

                        </td>
                        <td>
                            {convertPriceWithCurrency price=$value.product.price currency=$currency}
                        </td>
                        <td>
                            <span style="background-color:#4169E1; border-color:#4169E1;" class="label">{l s='Pending'}</span>
                            <br>
                            <a class="label label-success" href="{$value.link_edit_design}">{l s='Edit Design'}</a>
                        </td>
                    </tr>
                {/foreach}
            {/if}
        </tbody>
    </table>
</section>