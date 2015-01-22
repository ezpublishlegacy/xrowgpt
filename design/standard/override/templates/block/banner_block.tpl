{if $block.view|eq("pos1")}
    <div class="xrowgpt_ad" id="ad_flowblock1">
        {ad_code(array("oms_gpt_fullbanner1","oms_gpt_superbanner1","div-gpt-ad-1363251388018-1"))}
    </div>
{elseif $block.view|eq("pos2")}
    <div class="xrowgpt_ad" id="ad_flowblock2">
        {ad_code(array("oms_gpt_fullbanner2","oms_gpt_superbanner2","div-gpt-ad-1363251388018-2"))}
    </div>
{elseif or($block.view|eq("pos1_special"), $block.view|eq("standard"))}
    <div class="xrowgpt_ad" id="ad_flowblock1">
        {ad_code(array("oms_gpt_fullbanner1","div-gpt-ad-1363251388018-1"))}
    </div>
{elseif $block.view|eq("pos2_special")}
    <div class="xrowgpt_ad" id="ad_flowblock2">
        {ad_code(array("oms_gpt_fullbanner2","div-gpt-ad-1363251388018-2"))}
    </div>
{/if}
