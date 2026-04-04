from __future__ import annotations
import logging, discord
from discord.ext import commands
from typing import TYPE_CHECKING
from sys import modules as sysmodules
if TYPE_CHECKING:
	from utils.bot import Bot
from utils.bot import debug_only
from cogs import EXTENSIONS
__reload_deps__ = ()

async def reload_autocomplete(interaction: discord.Interaction, current: str):
	options = [
		discord.app_commands.Choice(name=i, value=i)
		for i in [i.removeprefix("cogs.") for i in EXTENSIONS] if current.lower() in i.lower()
	]
	if current.lower() in "all":
		options.append(discord.app_commands.Choice(name="all", value="all"))
	return options
class OwnerCog(commands.Cog):
	bot:'Bot'
	def __init__(self, bot:'Bot'):
		self.bot = bot
		self.logger = logging.getLogger(__name__)
		self.logger.setLevel(bot.logLevel)
		self.logger.debug("Owner Commands initialized")
	async def interaction_check(self, interaction:discord.Interaction):
		if interaction.user.id in [332030423913725953, 709449854371364895]:
			return True
		if not interaction.response.is_done():
			await interaction.response.send_message("You are not authorized to use this command.", ephemeral=True)
		return False

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	@discord.app_commands.autocomplete(extension=reload_autocomplete)
	async def reload(self, interaction:discord.Interaction, extension:str):
		await interaction.response.defer(ephemeral=True)
		try:
			if extension.lower() == "modules.db":
				self.bot.reload_db()
				await interaction.edit_original_response(content=f"Successfully reloaded the database.")
			elif extension.lower() == "all":
				for ext in EXTENSIONS:
					module = sysmodules.get(ext)
					deps = getattr(module, "__reload_deps__", ())
					await self.bot.reload_extension_with_deps(ext, *deps)
				await interaction.edit_original_response(content="Reloaded all cogs")
			else:
				full = f"cogs.{extension}"
				module = sysmodules.get(full)
				deps = getattr(module, "__reload_deps__", ()) if module else ()
				await self.bot.reload_extension_with_deps(full, *deps)
				await interaction.edit_original_response(content=f"Reloaded cog '{extension}'")
				self.logger.info(f"Reloaded cog '{extension}'")
		except:
			self.logger.exception(f"An error occured while reloading cog '{extension}'")
			await interaction.edit_original_response(content=f"Failed to reload '{extension}'")
	
	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	@debug_only()
	async def alt_admin(self, interaction:discord.Interaction, value:bool):
		await interaction.response.defer(ephemeral=True)
		global altAdminPerm
		altAdminPerm = value
		await interaction.edit_original_response(content=f"Value set to '{value}'")

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	async def force_sync(self, interaction:discord.Interaction):
		await interaction.response.defer(thinking=True)
		try:
			synced = await self.bot.tree.sync()
			self.logger.info(f"Synced {len(synced)} command(s)")
			await interaction.edit_original_response(content=f"Synced {len(synced)} command(s)")
		except Exception:
			self.logger.exception("An error occured while syncing")
			await interaction.edit_original_response(content="An error occurred while syncing")

async def setup(bot: 'Bot'):
	await bot.add_cog(OwnerCog(bot))